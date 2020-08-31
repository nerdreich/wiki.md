<?php

/**
 * Copyright 2020 Markus Leupold-Löwenthal
 *
 * This file is part of wiki.md.
 *
 * wiki.md is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * wiki.md is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with wiki.md. If not, see <https://www.gnu.org/licenses/>.
 */

namespace at\nerdreich;

require_once('lib/spyc.php'); // yaml parser

/**
 * UserSession - user-permission handling.
 *
 * This class can verify that a client is allowed to do certain things in a tree-
 * based content structure. Permissions are arbitrary strings stored in `_.yaml`
 * files in the tree. If the UserSession class has to verify that a user may do
 * something in the tree, it accesses the _.yaml file in the directory in
 * question and checks if the permission is granted for his username. If not,
 * it traverse up the tree till either that permission is granted, denied or the
 * root of the tree is reached.
 *
 * Notes:
 *
 * - Users are authenticated via the .htpasswd file in the data/ folder next to
 *   this php file.
 * - If simple logins are enabled in the config file, users are identified only
 *   via password, not usernames. No two users can have the same password then.
 * - Users have an alias they can set after login and even change while logged
 *   in. This is not the username.
 */
class UserSession
{
    private $htpasswd = '';
    private $config = [];
    private $username = '*';  // asterisk is anonymous/not logged-in user

    private $superuser = 'admin';
    private $datadir = 'data';
    private $contentdir = 'data/content';

    /**
     * Constructor
     *
     * @param string $urlPath The path to check permissions for.
     * @param string $contentdir The (sub)directory where the markdown files are stored.
     */
    public function __construct(
        array $config
    ) {
        $this->config = $config;
        $this->htpasswd = dirname(dirname(__FILE__)) . '/' . $this->datadir . '/.htpasswd';
        $this->datadir = $this->config['datafolder'] ?? 'data';
        $this->contentdir = dirname(dirname(__FILE__)) . '/' . $this->datadir . '/content';
        $this->username = $this->resumeSession();
    }

    /**
     * Get the name of the superuser.
     *
     * @return string Superuser name.
     */
    public function getSuperuser(): string
    {
        return $this->superuser;
    }

    /**
     * Try to authenticate and log-in a user.
     *
     * There are two login modes: regular (default) and simple. In simple mode
     * only the password is used and the first user with that password in the DB
     * is logged in. Consider this "page passwords" instead of "user passwords".
     *
     * @param string $username The name to check for.
     * @param string $secret The secret/password to check.
     * @return bool True if user has been logged in and a new session started.
     */
    public function login(
        string $username,
        string $secret
    ): bool {
        if ($this->config['login_simple']) { // password-only logins
            $user = $this->getUserForPassword($secret);
        } else {
            $user = $this->verifyLogin($username, $secret);
        }
        if ($user) {
            // session_start();
            session_start();
            session_regenerate_id(false);
            session_unset();
            session_start();
            $_SESSION['username'] = $user;
            $_SESSION['alias'] = '';
            return true;
        }
        return false;
    }

    /**
     * Get the content of the user db file (.htpasswd).
     *
     * @return array One entry per line.
     */
    private function fetchHtpasswd(): array
    {
        return file($this->htpasswd);
    }

    /**
     * Get the content of the user db parsed into an array.
     *
     * @return array User->bcrypt array.
     */
    private function loadAllUsers(): array
    {
        $data = [];
        foreach ($this->fetchHtpasswd() as $line) {
            list($username, $hash) = explode(':', trim($line));
            $data[$username] = $hash;
        }
        return $data;
    }

    /**
     * Read the content of a permission YAML file from disk.
     *
     * @param string $path WikiPath to fetch the file for.
     * @return array YAML data parsed into an array or null if not found.
     */
    private function loadPermissionFile(
        string $path
    ): ?array {
        $filename = $this->contentdir . $path . '_.yaml';
        if (is_file($filename)) {
            return \Spyc::YAMLLoadString(file_get_contents($filename));
        }
        return null;
    }

    /**
     * Write a user array back to the filesystem.
     *
     * @param array $users User->bcrypt array.
     */
    private function persistUsers(array $users): void
    {
        $content = '';
        foreach ($users as $username => $secret) {
            $content .= $username . ':' . $secret . "\n";
        }
        file_put_contents($this->htpasswd, $content, LOCK_EX);
    }

    /**
     * Write a permission _.yaml back to the filesystem.
     *
     * If permissions are empty, it will remove the now unnecessary file.
     *
     * @param string $path WikiPath to write the file into.
     * @param array $yaml Array containing user... entries. Will not be validated.
     */
    private function persistPermissions(
        string $path,
        array $yaml
    ): void {
        $filename = $this->contentdir . $path . '_.yaml';

        // create parent dir if necessary
        if (!\file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }

        // delete yaml file if no entries remain
        if (count($yaml) <= 0 && file_exists($filename)) {
            unlink($filename);
            return;
        }

        // write file
        file_put_contents($filename, \Spyc::YAMLDump($yaml), LOCK_EX);
    }

    /**
     * Reverse-Lookup a user in our .htpasswd file via a password.
     *
     * Only used in simple login mode.
     *
     * @param string $password Cleartext password to look up.
     * @return string Username if found, or null if no machting user exists.
     */
    private function getUserForPassword(
        string $password
    ): ?string {
        foreach ($this->loadAllUsers() as $username => $hash) {
            if (password_verify($password, trim($hash))) {
                return trim($username);
            }
        }
        return null;
    }

    /**
     * Lookup a user/password pair in our .htpasswd.
     *
     * @param string $name User to look up.
     * @param string $password Cleartext password to verify.
     * @return string Username if found, or null if no machting user exists.
     */
    private function verifyLogin(
        string $name,
        string $password
    ): ?string {
        foreach ($this->loadAllUsers() as $username => $hash) {
            if ($username === $name) {
                if (password_verify($password, trim($hash))) {
                    return trim($username);
                } else {
                    return null; // failed
                }
            }
        }
        return null;
    }

    /**
     * Destroy the session to log-out the user.
     *
     * Also makes sure the session cookie gets removed. This is done to better
     * comply with GDPR by not having cookies for non-logged-in users.
     */
    public function logout(): void
    {
        // logout on server
        session_start();
        session_unset();
        session_destroy();

        // remove PHP session cookie from client to better comply to GDPR
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            0,
            $params['path'],
            $params['domain'],
            $params['secure'],
            isset($params['httponly'])
        );
    }

    /**
     * Try to pick up an previous session.
     *
     * Takes care to only use PHP's start_session if there actually was one,
     * otherwise a session cookie would be created for the logged-out user.
     *
     * @return string Username of re-attached session, or '*' for logged-out users.
     */
    public function resumeSession(): string
    {
        // do not autostart sessions to not create unwanted cookies
        if (array_key_exists('PHPSESSID', $_COOKIE)) {
            session_start();
            return $_SESSION['username'] ?? '';
        }
        return '*'; // no session -> anonmyous user
    }

    /**
     * Determine if we have a logged-in user.
     *
     * @return bool True if there is a logged-in user.
     */
    public function isLoggedIn(): bool
    {
        return !$this->isLoggedOut();
    }

    /**
     * Determine if we do not have a logged-in user.
     *
     * @return bool True if no user is currently logged-in in this session.
     */
    public function isLoggedOut(): bool
    {
        return session_status() === PHP_SESSION_NONE;
    }

    /**
     * Get something unique for this session.
     *
     * Only to be used for non-critical things.
     *
     * @return string One-way-hash value of the session ID.
     */
    public function getSessionToken(): string
    {
        return md5($_COOKIE['PHPSESSID']);
    }

    /**
     * Get the logged-in username (if any).
     *
     * @return string Author name, e.g. 'Yuki'.
     */
    public function getAlias(): string
    {
        return $_SESSION['alias'] ?? '';
    }

    /**
     * Set the logged-in username.
     *
     * @param string $alias Author name, e.g. 'Yuki'.
     */
    public function setAlias(
        string $alias
    ): void {
        $_SESSION['alias'] = $alias;
    }

    /**
     * Check if the current user has a given permissio in the file tree.
     *
     * Rules for checking:
     * - if folder contains _.yaml and it mentions the user -> OK
     * - if not, go up a folder and check there, till found
     * - if no permission is found even in the root -> NOK
     * - a '*' permission at any time allows everyone
     *
     * @param string $username A username to check for.
     * @param string $permission Name of permission.
     * @param string $path A path to check the permission for.
     * @return boolean True, if the permission is granted.
     */
    public function hasExplicitPermission(
        string $username,
        string $permission,
        string $path
    ): bool {
        // we start at the content dir and traverse up from here
        $scanpath = preg_replace('/[^\/]*$/', '', $path);

        while (strlen($scanpath) > 0) { // path left to traverse
            if ($yaml = $this->loadPermissionFile($scanpath)) {
                // check if user is explicitly listed
                // note that "any user" and "anonymous" are both '*'
                if (array_key_exists($permission, $yaml)) {
                    $permissions = explode(',', trim($yaml[$permission] ?? ''));
                    if (in_array($username, $permissions) || in_array('*', $permissions)) {
                        return true; // -> ALLOWED
                    }
                    return false; // there were explicit permissions and none matched -> DENIED
                }
            }

            // go up the tree
            $scanpath = preg_replace('/[^\/]*\/$/', '', $scanpath);
        }

        return false;
    }

    /**
     * Check if the current user may read/view a path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayCreate(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userCreate', $path);
    }

    /**
     * Check if the current user may edit/create a path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayRead(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userRead', $path);
    }

    /**
     * Check if the current user may read/view a path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayUpdate(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userUpdate', $path);
    }

    /**
     * Check if the current user may edit/create a path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayDelete(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userDelete', $path);
    }

    /**
     * Check if the current user may administrate a path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayAdmin(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userAdmin', $path);
    }

    /**
     * Check if the current user may administrate media & uploads.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayMedia(
        string $path
    ): bool {
        return $this->username === $this->superuser ||
            $this->hasExplicitPermission($this->username, 'userMedia', $path);
    }

    // --- UI methods ----------------------------------------------------------

    /**
     * Load all data for the administration page.
     *
     * @param string $path WikiPath of folder to administer.
     * @param array Array containing 'permissions' and 'users'.
     */
    public function adminFolder(
        string $path
    ): ?array {
        if (preg_match('/[^\/]$/', $path)) {
            $path = dirname($path); // folder of files
            $path = $path === '/' ? '/' : $path . '/';
        }
        if ($this->mayAdmin($path)) {
            $infos['folder'] = $path;

            if ($yaml = $this->loadPermissionFile($path)) {
                foreach (['userCreate', 'userRead', 'userUpdate', 'userDelete', 'userAdmin', 'userMedia'] as $permission) {
                    if (array_key_exists($permission, $yaml)) {
                        $permissions[$permission] = $yaml[$permission];
                    }
                }
            }
            $infos['permissions'] = $permissions;

            foreach ($this->loadAllUsers() as $username => $hash) {
                $users[] = $username;
            }
            $infos['users'] = $users;

            return $infos;
        }
        return null;
    }

    /**
     * (Try to) Delete a user.
     *
     * Will just remove it from the user db, not the _.yaml pages.
     *
     * @param string $username User to delete.
     * @param bool True if successfull.
     */
    public function deleteUser(
        string $username
    ): bool {
        if ($username !== $this->superuser && $this->mayAdmin('/')) {
            $username = preg_replace('/[^a-zA-Z]+/', '', $username);
            $users = $this->loadAllUsers();
            if (array_key_exists($username, $users)) {
                $users = \array_diff_key($users, [$username => 'delete']);
                $this->persistUsers($users);
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Add a password to the user db.
     *
     * If the user already exists, the password is set to the new secret.
     * Otherwise, a new user entry is added.
     *
     * @param string $username User to add/set. Only letters, 2-32 length.
     * @param string $secret Password to add/set. No whitespace. 6-128 length.
     * @param bool True if successfull.
     */
    public function addSecret(
        string $username,
        string $secret
    ): bool {
        if ($this->mayAdmin('/')) {
            $username2 = preg_replace('/[^a-zA-Z]+/', '', $username);
            if ($username2 !== $username || strlen($username2) < 2 || strlen($username2) > 32) {
                return false;
            }
            $secret2 = preg_replace('/[\s]+/', '', $secret);
            if ($secret2 !== $secret || strlen($secret2) < 6 || strlen($secret2) > 128) {
                return false;
            }
            $users = $this->loadAllUsers();
            $users[$username2] = password_hash(trim($secret2), PASSWORD_BCRYPT);
            $this->persistUsers($users);
            return true;
        }
        return false;
    }

    /**
     * Prepare user list to be written into a yaml file.
     *
     * Will sort entries, eliminate duplicates/non-exisiting user and handle '*'.
     *
     * @param array $permissionList A list of users/permissions.
     * @param array $userDB The user DB as provided by loadAllUsers().
     * @return string Comma-separated userlist or null if empty.
     */
    private function sanitizeUserlist(array $userList, array $userDB): ?string
    {
        $userList = array_unique($userList);

        // if at least one asterisk is there, additional individual users are pointless
        if (in_array('*', $userList)) {
            return '*';
        }

        // remove non-existing users
        $finalPermissionList = [];
        foreach ($userList as $user) {
            if (array_key_exists($user, $userDB)) {
                $finalPermissionList[] = $user;
            }
        }

        sort($finalPermissionList);
        return $finalPermissionList === [] ? null : implode(',', $finalPermissionList);
    }

    /**
     * Set permissions on a folder.
     *
     * Will remove unknown users and replace all old values.
     *
     * @param string $path The path to set the permissions for.
     * @param array $userCreate Array of users allowed to create.
     * @param array $userRead Array of users allowed to view.
     * @param array $userUpdate Array of users allowed to edit.
     * @param array $userDelete Array of users allowed to delete.
     * @param array $userAdmin Array of users allowed to administrate.
     * @return bool True if successfull.
     */
    public function setPermissions(
        string $path,
        array $userCreate,
        array $userRead,
        array $userUpdate,
        array $userDelete,
        array $userMedia,
        array $userAdmin
    ): bool {
        if (preg_match('/\/$/', $path) && $this->mayAdmin($path)) { // can only set permissions on folders
            $userDB = $this->loadAllUsers();

            $yaml = [];
            if ($userCreate = $this->sanitizeUserlist($userCreate, $userDB)) {
                $yaml['userCreate'] = $userCreate;
            }
            if ($userRead = $this->sanitizeUserlist($userRead, $userDB)) {
                $yaml['userRead'] = $userRead;
            }
            if ($userUpdate = $this->sanitizeUserlist($userUpdate, $userDB)) {
                $yaml['userUpdate'] = $userUpdate;
            }
            if ($userDelete = $this->sanitizeUserlist($userDelete, $userDB)) {
                $yaml['userDelete'] = $userDelete;
            }
            if ($userMedia = $this->sanitizeUserlist($userMedia, $userDB)) {
                $yaml['userMedia'] = $userMedia;
            }
            if ($userAdmin = $this->sanitizeUserlist($userAdmin, $userDB)) {
                $yaml['userAdmin'] = $userAdmin;
            }
            $this->persistPermissions($path, $yaml);
            return true;
        }
        return false;
    }
}
