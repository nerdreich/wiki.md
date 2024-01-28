<?php // phpcs:ignore

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

if (!class_exists('\at\nerdreich\wiki\UserPlugin')) {

    /**
     * User/Permission manager plugin for wiki.md.
     *
     * Adds management UIs to add users, change passwords and set page
     * permissions by editing .htpasswd and _.yaml files.
     *
     * This only adds the UI - the permission features are built into the core.
     */
    class UserPlugin extends WikiPlugin
    {
        public function setup()
        {
            $this->wiki->registerActionRoute('user', 'list', function ($wiki) {
                if ($this->list($wiki->core->getWikiPath()) !== null) {
                    $wiki->renderPluginFile('user', 'list');
                }
                $wiki->renderLoginOrDenied(); // transparent login
            });

            $this->wiki->registerActionRoute('user', 'delete', function ($wiki) {
                if ($this->deleteUser($_GET['name'])) {
                    $wiki->redirect($wiki->core->getLocation(), 'user=list');
                }
            });

            $this->wiki->registerActionRoute('user', 'set', function ($wiki) {
                if (
                    $this->setPermissions(
                        $wiki->core->getWikiPath(),
                        [
                            'pageCreate' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['pageCreate'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            ),
                            'pageRead' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['pageRead'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            ),
                            'pageUpdate' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['pageUpdate'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            ),
                            'pageDelete' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['pageDelete'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            ),
                            'mediaAdmin' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['mediaAdmin'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            ),
                            'userAdmin' => preg_split(
                                '/,/',
                                preg_replace('/\s+/', '', $_POST['userAdmin'] ?? ''),
                                -1,
                                PREG_SPLIT_NO_EMPTY
                            )
                        ]
                    )
                ) {
                    $wiki->redirect($wiki->core->getLocation(), 'user=list');
                }
            });

            $this->wiki->registerActionRoute('user', 'secret', function ($wiki) {
                if ($this->addSecret($_POST['username'], $_POST['secret'])) {
                    $wiki->redirect($wiki->core->getLocation(), 'user=list');
                }
            });

            if ($this->mayUserAdmin($this->core->getWikiPath())) {
                $this->wiki->addMenuItem('user=list', 'Permissions');
            }
        }

        /**
         * Check if the current user may administrate media & uploads.
         *
         * @param string $path The path to check the permission for.
         * @return boolean True, if permissions are sufficient. False otherwise.
         */
        public function mayUserAdmin(
            ?string $wikiPath = null
        ): bool {
            $wikiPath = $wikiPath ?? $this->core->getWikiPath();
            return $this->user->hasPermission('userAdmin', $wikiPath);
        }

        /**
         * Load all data for the administration page.
         *
         * @param string $path WikiPath of folder to administer.
         * @param array Array containing 'permissions' and 'users'.
         */
        public function list(
            string $wikiPath = null
        ): ?array {
            $wikiPath = $wikiPath ?? $this->core->getWikiPath();
            if ($wikiPath[-1] !== '/') {
                $wikiPath = dirname($wikiPath); // folder of files
                $wikiPath = $wikiPath === '/' ? '/' : $wikiPath . '/';
            }
            if ($this->mayUserAdmin($wikiPath)) {
                $infos['folder'] = $wikiPath;

                $permissions = [];
                if ($yaml = $this->user->loadPermissionFileFS($wikiPath)) {
                    // TODO: remove hardcoded values from plugins
                    foreach (
                        [
                        'pageCreate',
                        'pageRead',
                        'pageUpdate',
                        'pageDelete',
                        'userAdmin',
                        'mediaAdmin'
                        ] as $permission
                    ) {
                        if (array_key_exists($permission, $yaml)) {
                            $permissions[$permission] = $yaml[$permission];
                        }
                    }
                }
                $infos['permissions'] = $permissions;

                foreach ($this->user->loadUserFileFS() as $username => $hash) {
                    $users[] = $username;
                }
                $infos['users'] = $users;

                return $infos;
            }
            return null;
        }

        // -------------------------------------------------------------------------
        // --- user management -----------------------------------------------------
        // -------------------------------------------------------------------------

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
            if ($username !== $this->user->getSuperuser() && $this->mayUserAdmin('/')) {
                $username = preg_replace('/[^a-zA-Z]+/', '', $username);
                $users = $this->user->loadUserFileFS();
                if (array_key_exists($username, $users)) {
                    $users = \array_diff_key($users, [$username => 'delete']);
                    $this->persistUsersFS($users);
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
            if ($this->mayUserAdmin('/')) {
                $username2 = preg_replace('/[^a-zA-Z]+/', '', $username);
                if ($username2 !== $username || strlen($username2) < 2 || strlen($username2) > 32) {
                    return false;
                }
                $secret2 = preg_replace('/[\s]+/', '', $secret);
                if ($secret2 !== $secret || strlen($secret2) < 6 || strlen($secret2) > 128) {
                    return false;
                }
                $users = $this->user->loadUserFileFS();
                $users[$username2] = password_hash(trim($secret2), PASSWORD_BCRYPT);
                $this->persistUsersFS($users);
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
         * @param array $userDB The user DB as provided by loadUserFileFS().
         * @return string Comma-separated userlist or null if empty.
         */
        private static function sanitizeUserlist(array $userList, array $userDB): ?string
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
         * Write a user array back to the filesystem.
         *
         * @param array $users User->bcrypt array.
         */
        private function persistUsersFS(array $users): void
        {
            $content = '';
            foreach ($users as $username => $secret) {
                $content .= $username . ':' . $secret . "\n";
            }
            file_put_contents($this->user->getHtpasswd(), $content, LOCK_EX);
        }

        // -------------------------------------------------------------------------
        // --- permission management -----------------------------------------------
        // -------------------------------------------------------------------------

        /**
         * Set permissions on a folder.
         *
         * Will remove unknown users and replace all old values.
         *
         * @param string $path The path to set the permissions for.
         * @param array $permissions Array of permission => ['list', 'of', 'users'].
         * @return bool True if successfull.
         */
        public function setPermissions(
            string $path,
            array $permissions
        ): bool {
            if ($path[-1] === '/' && $this->mayUserAdmin($path)) { // can only set permissions on folders
                $userDB = $this->user->loadUserFileFS();

                $yaml = [];
                foreach ($permissions as $permission => $users) {
                    if ($userlist = $this->sanitizeUserlist($users, $userDB)) {
                        $yaml[$permission] = $userlist;
                    }
                }

                $this->persistPermissionsFS($path, $yaml);
                return true;
            }
            return false;
        }

        /**
         * Write a permission _.yaml back to the filesystem.
         *
         * If permissions are empty, it will remove the now unnecessary file.
         *
         * @param string $path WikiPath to write the file into.
         * @param array $yaml Array containing user... entries. Will not be validated.
         */
        private function persistPermissionsFS(
            string $path,
            array $yaml
        ): void {
            $filename = $this->core->getContentDirFS() . $path . '_.yaml';

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
    }

    $GLOBALS['wiki.md-plugins']['user'] = '\at\nerdreich\wiki\UserPlugin';
}
