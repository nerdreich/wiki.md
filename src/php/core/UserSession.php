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
 * Important: This is more of a "page password" implementation, not a real user
 * login implementation.
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
 * - Users are identified only via password, not usernames. Therefore no two
 *   users can have the same password.
 * - Users have an alias they can set after login and even change while logged
 *   in. This is not the username.
 */
class UserSession
{
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
        $this->datadir = $this->config['datafolder'] ?? 'data';
        $this->contentdir = dirname(dirname(__FILE__)) . '/' . $this->datadir . '/content';
        $this->username = $this->resumeSession();
    }

    /**
     * Try to authenticate and log-in a user.
     *
     * This is only done via the password. No two users can have the same
     * password. Consider them 'page passwords' if it helps ;)
     *
     * @param string $secret The secret/password to check.
     * @return bool True if user has been logged in and a new session started.
     */
    public function login(
        string $secret
    ): bool {
        $user = $this->getUserForPassword($secret);
        if ($user) {
            session_start();
            $_SESSION['username'] = $user;
            $_SESSION['alias'] = '';
            return true;
        }
        return false;
    }

    /**
     * Lookup a user in our .htpasswd file via a password.
     *
     * @param string $password Cleartext password to look up.
     * @return string Username if found, or null if no machting user exists.
     */
    private function getUserForPassword(
        string $password
    ): ?string {
        $htpasswd = file(dirname(dirname(__FILE__)) . '/' . $this->datadir . '/.htpasswd');
        foreach ($htpasswd as $line) {
            list($username, $hash) = explode(':', $line);
            if (password_verify($password, trim($hash))) {
                return trim($username);
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
        return session_status() == PHP_SESSION_NONE;
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
     * @param string $permission Name of permission.
     * @return boolean True, if the permission is granted.
     */
    private function hasExplicitPermission(
        string $path,
        string $permission
    ): bool {
        // we start at the content dir and traverse up from here
        $scanpath = preg_replace('/[^\/]*$/', '', $path);

        while (strlen($scanpath) > 0) { // path left to traverse
            $scanfile = $this->contentdir . $scanpath . '_.yaml';
            if (is_file($scanfile)) {
                $yaml = \Spyc::YAMLLoadString(file_get_contents($scanfile));

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
     * Check if the current user may read/view the current path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayCreate(
        string $path
    ): bool {
        return $this->username === $this->superuser || $this->hasExplicitPermission($path, 'userCreate');
    }

    /**
     * Check if the current user may edit/create the current path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayRead(
        string $path
    ): bool {
        return $this->username === $this->superuser || $this->hasExplicitPermission($path, 'userRead');
    }

    /**
     * Check if the current user may read/view the current path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayUpdate(
        string $path
    ): bool {
        return $this->username === $this->superuser || $this->hasExplicitPermission($path, 'userUpdate');
    }

    /**
     * Check if the current user may edit/create the current path.
     *
     * @param string $path The path to check the permission for.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayDelete(
        string $path
    ): bool {
        return $this->username === $this->superuser || $this->hasExplicitPermission($path, 'userDelete');
    }
}
