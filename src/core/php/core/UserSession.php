<?php

/**
 * Copyright 2020-2022 Markus Leupold-Löwenthal
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

namespace at\nerdreich\wiki;

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
 * - If simple logins are enabled, users are identified only via password, not
 *   usernames. No two users can have the same password then.
 * - Users have an alias they can set after login and even change while logged
 *   in. This is not the username.
 */
class UserSession
{
    private $htpasswd = '';
    private $simpleLogins = false;
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
        ?string $datadir,
        ?bool $simple
    ) {
        $this->htpasswd = dirname(dirname(__FILE__)) . '/' . $this->datadir . '/.htpasswd';
        $this->datadir = $datadir ?? 'data';
        $this->simpleLogins = $simple ?? false;
        $this->contentdir = dirname(dirname(__FILE__)) . '/' . $this->datadir . '/content';
        $this->username = $this->resumeSession();
    }

    // -------------------------------------------------------------------------
    // --- login/logout/session handling ---------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Determine if this wiki runs in simple login mode (=password only)
     *
     * @return bool True, if simple logins are enabled in config.
     */
    public function isLoginSimple(): bool
    {
        return $this->simpleLogins;
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
        if ($this->isLoginSimple()) { // password-only logins
            $user = $this->getUserForPassword($secret);
        } else {
            $user = $this->verifyLogin($username, $secret);
        }
        if ($user) {
            // session_start() might be run by constructor
            if (session_status() !== PHP_SESSION_NONE) {
                session_destroy();
                session_unset();
            }
            session_start();
            session_regenerate_id(false);
            $_SESSION['username'] = $user;
            $_SESSION['alias'] = '';

            return true;
        }
        return false;
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
        foreach ($this->loadUserFileFS() as $username => $hash) {
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
        foreach ($this->loadUserFileFS() as $username => $hash) {
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
        // session_start() might be run by constructor
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }

        // remove PHP session cookie from client to better comply to GDPR
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            0,
            $params['path'] ?? '',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? false
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
        return md5($_COOKIE['PHPSESSID'] ?? 'none');
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

    // -------------------------------------------------------------------------
    // --- user management -----------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Get the user db file.
     *
     * @return string Absolute path.
     */
    public function getHtpasswd(): string
    {
        return $this->htpasswd;
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
     * Get the content of the user db parsed into an array.
     *
     * @return array User->bcrypt array.
     */
    public function loadUserFileFS(): array
    {
        $data = [];
        foreach (file($this->htpasswd) as $line) {
            list($username, $hash) = explode(':', trim($line));
            $data[$username] = $hash;
        }
        return $data;
    }

    // -------------------------------------------------------------------------
    // --- permission management -----------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Read the content of a permission YAML file from disk.
     *
     * @param string $path WikiPath to fetch the file for.
     * @return array YAML data parsed into an array or null if not found.
     */
    public function loadPermissionFileFS(
        string $path
    ): ?array {
        $filename = $this->contentdir . $path . '_.yaml';
        if (is_file($filename)) {
            return \Spyc::YAMLLoadString(file_get_contents($filename));
        }
        return null;
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
     * @param string $permission Name of permission.
     * @param string $path A path to check the permission for.
     * @return boolean True, if the permission is granted.
     */
    private function hasExplicitPermission(
        string $permission,
        string $path
    ): bool {
        // we start at the content dir and traverse up from here
        $scanpath = preg_replace('/[^\/]*$/', '', $path);

        while (strlen($scanpath) > 0) { // path left to traverse
            if ($yaml = $this->loadPermissionFileFS($scanpath)) {
                // check if user is explicitly listed
                // note that "any user" and "anonymous" are both '*'
                if (array_key_exists($permission, $yaml)) {
                    $permissions = explode(',', trim($yaml[$permission] ?? ''));
                    if (in_array($this->username, $permissions) || in_array('*', $permissions)) {
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
     * Determine if the current user/session is a super-user.
     *
     * @return bool True if superuser.
     */
    public function isSuperuser(): bool
    {
        return $this->username === $this->superuser;
    }

    /**
     * Check if the current has a permission on a path.
     *
     * @param string $permission The permission to check for.
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient or user is superuser.
     */
    public function hasPermission(
        string $permission,
        string $path
    ): bool {
        return $this->isSuperuser() || $this->hasExplicitPermission($permission, $path);
    }
}
