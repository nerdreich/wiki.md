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

// --- load config -------------------------------------------------------------

$config = parse_ini_file('data/.config.ini');

// --- setup user session ------------------------------------------------------

require_once('wiki.usersession.php');
$user = new at\nerdreich\UserSession('data/content');

// --- frontend helpers --------------------------------------------------------

/**
 * Terminate further execution and redirect the client to another page.
 *
 * Has to be called before any output is sent to client or setting headers will
 * fail.
 *
 * @param string $path URL path to send user to.
 * @param string $path Wiki action to add to path.
 */
function redirect(
    string $path,
    string $action
) {
    header('Location: ' . $path . $action);
    exit; // terminate execution to enforce redirect
}

/**
 * Remove 'bad' stuff from a path so no one can break out of the docroot.
 *
 * @param string $path Path to sanitize.
 * @return string A path with invalid characters and '..' tricks removed.
 */
function sanitizePath(
    string $path
): string {
    $path = urldecode($path);
    $path = mb_ereg_replace('([^\w\s\d\-_~,;/\[\]\(\).])', '', $path); // only whitelisted chars
    $path = mb_ereg_replace('([\.]{2,})', '', $path); // no '..'
    $path = mb_ereg_replace('^/*', '/', $path); // make sure there is only one leading slash
    return $path;
}

// --- setup wiki --------------------------------------------------------------

require_once('wiki.cms.php');
$wiki = new at\nerdreich\Wiki('data/content');

// --- setup theme --------------------------------------------------------------

$config['themePath'] = $wiki->getPathRoot() . '/themes/' . $config['theme'] . '/';
$config['themeRoot'] = dirname(__FILE__) . '/themes/' . $config['theme'] . '/';
require_once($config['themeRoot'] . 'setup.php');

// --- route requests ----------------------------------------------------------

// determine content path. will trim folder if wiki.md is installed in a sub-folder.
$contentPath = substr(sanitizePath(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
), strlen($wiki->getPathRoot()));

$wiki->load($contentPath);

// first we check for any authentication related stuff
switch ($_GET['auth']) {
    case 'login':
        if ($user->login(trim($_POST['password']))) {
            // successfull -> redirect back
            $action = array_key_exists('action', $_GET) ? '?action=' . urlencode($_GET['action']) : '';
            redirect($wiki->getPathRoot() . $contentPath, $action);
        } else {
            // unsuccessful -> show login again
            include($config['themeRoot'] . 'login.php');
            exit;
        }
        break;
    case 'logout':
        $user->logout();
        $action = array_key_exists('action', $_GET) ? '?action=' . urlencode($_GET['action']) : '';
        redirect($wiki->getPathRoot() . $contentPath, $action);
}

// now check for regular wiki operations
if (!$wiki->exists()) {
    switch ($_GET['action']) {
        case 'createPage':
            if ($user->mayCreate($contentPath)) {
                $wiki->createPage();
                include($config['themeRoot'] . 'edit.php');
                exit;
            }
            break;
        case 'save': // saving new pages
            if ($user->mayUpdate($contentPath)) {
                $user->setAlias(trim($_POST['author']));
                $wiki->savePage(
                    trim($_POST['content']),
                    trim($_POST['title']),
                    trim($user->getAlias())
                );
                exit;
            }
            break;
        default:
            include($config['themeRoot'] . '404.php');
            exit;
    }
} else {
    switch ($_GET['action']) {
        case 'delete':
            if ($user->mayDelete($contentPath)) {
                include($config['themeRoot'] . 'delete.php');
                exit;
            }
            break;
        case 'deleteOK':
            if ($user->mayDelete($contentPath)) {
                $user->deletePage();
                exit;
            }
            break;
        case 'edit':
            if ($user->mayRead($contentPath) && $user->mayUpdate($contentPath)) {
                include($config['themeRoot'] . 'edit.php');
                exit;
            }
            break;
        case 'save': // saving existing pages
            if ($user->mayUpdate($contentPath)) {
                $user->setAlias(trim($_POST['author']));
                $wiki->savePage(
                    trim($_POST['content']),
                    trim($_POST['title']),
                    trim($user->getAlias())
                );
                exit;
            }
            break;
        case 'history':
            if ($user->mayRead($contentPath) && $user->mayUpdate($contentPath)) {
                include($config['themeRoot'] . 'history.php');
                exit;
            }
            break;
        case 'restore':
            if ($user->mayRead($contentPath) && $user->mayUpdate($contentPath)) {
                $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
                if ($version > 0) {
                    $wiki->revertToVersion($version);
                }
                include($config['themeRoot'] . 'edit.php');
                exit;
            }
            break;
        default:
            if ($user->mayRead($contentPath)) {
                include($config['themeRoot'] . 'view.php');
                exit;
            }
    }
}

// if we got here, then no 'exit' fired - probably a permission error
include($config['themeRoot'] . 'login.php');
