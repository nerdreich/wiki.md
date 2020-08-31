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

$config = parse_ini_file('data/config.ini');

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
    string $action = ''
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

/**
 * Output a theme file and terminate further execution.
 *
 * This puts the the theme file into a function scope, so it can only access
 * global-declared variables.
 *
 * @param string $filename Theme file to load, e.g. 'edit.php'.
 * @param int $httpResponseCode Code to send this page with to the client.
 */
function renderThemeFile(string $filename, $httpResponseCode = 200): void
{
    global $config, $wiki, $user;
    http_response_code($httpResponseCode);
    require($config['themeDirFS'] . '/' . $filename);
    exit;
}

/**
 * Show a permission-denied message for logged-in users or the login screen for
 * logged out users.
 */
function renderLoginOrDenied()
{
    global $user;
    if ($user->isLoggedIn()) {
        // as this user is logged in, (s)he just has insufficient permissions
        renderThemeFile('403.php', 403);
    } else {
        // user has to login-first
        renderThemeFile('login.php', 401);
    }
}

/**
 * Deliver a file, usually an image or uploaded file, to the client.
 *
 * Will set proper HTTP headers and terminate execution after sending the blob.
 *
 * @param string $pathFS Absolute path of file to send.
 */
function renderMedia(string $pathFS): void
{
    header('Content-Type:' . mime_content_type($pathFS));
    header('Content-Length: ' . filesize($pathFS));
    readfile($pathFS);
    exit;
}

// --- setup wiki --------------------------------------------------------------

require_once('core/Wiki.php');
require_once('core/UserSession.php');
$user = new at\nerdreich\UserSession($config);
$wiki = new at\nerdreich\Wiki($config, $user);

// --- setup theme --------------------------------------------------------------

$config['themePath'] = $wiki->getLocation('/themes/' . $config['theme']) . '/';
$config['themeDirFS'] = dirname(__FILE__) . '/themes/' . $config['theme'];
require_once($config['themeDirFS'] . '/setup.php');

// --- route requests ----------------------------------------------------------

// determine content path. will trim folder if wiki.md is installed in a sub-folder.
$contentPath = substr(sanitizePath(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
), strlen($wiki->getWikiRoot()));

$canonicalPath = $wiki->init($contentPath);
if ($contentPath != $canonicalPath) {
    redirect($canonicalPath);
}

if ($wiki->isMedia() && $user->mayRead($wiki->getWikiPath())) {
    renderMedia($wiki->getContentFileFS());
}

// first we check for any authentication related stuff
switch ($_GET['auth']) {
    case 'login':
        if ($user->login(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
            // successfull -> redirect back
            $action = array_key_exists('action', $_GET) ? '?action=' . urlencode($_GET['action']) : '';
            redirect($wiki->getLocation(), $action);
        } else {
            // unsuccessful -> show login again
            renderThemeFile('login.php', 401);
        }
        break;
    case 'logout':
        $user->logout();
        $action = array_key_exists('action', $_GET) ? '?action=' . urlencode($_GET['action']) : '';
        redirect($wiki->getLocation(), $action);
}

// now check for regular wiki operations

// actions that work on existing & non-existing pages
switch ($_GET['admin']) {
    case 'folder': // folder administration
        if ($user->adminFolder($contentPath) != null) {
            renderThemeFile('admin_folder.php');
        }
        break;
    case 'delete':
        if ($user->deleteUser($_GET['user'])) {
            redirect($wiki->getLocation() . '?admin=folder');
        }
        break;
    case 'permissions':
        if (
            $user->setPermissions(
                $contentPath,
                preg_split('/,/', preg_replace('/\s+/', '', $_POST['userCreate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                preg_split('/,/', preg_replace('/\s+/', '', $_POST['userRead'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                preg_split('/,/', preg_replace('/\s+/', '', $_POST['userUpdate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                preg_split('/,/', preg_replace('/\s+/', '', $_POST['userDelete'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                preg_split('/,/', preg_replace('/\s+/', '', $_POST['userAdmin'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
            )
        ) {
            redirect($wiki->getLocation() . '?admin=folder');
        }
        break;
    case 'secret':
        if ($user->addSecret($_POST['username'], $_POST['secret'])) {
            redirect($wiki->getLocation() . '?admin=folder');
        }
        break;
}
switch ($_GET['action']) {
    case 'save': // saving new pages
        $user->setAlias(trim(preg_replace('/\s+/', ' ', $_POST['author'])));
        if (
            $wiki->savePage(
                trim(str_replace("\r", '', $_POST['content'])),
                trim(preg_replace('/\s+/', ' ', $_POST['title'])),
                trim($user->getAlias())
            )
        ) {
            redirect($wiki->getLocation());
        };
        break;
}

if (!$wiki->exists()) {
    switch ($_GET['action']) {
        case 'createPage':
            if ($wiki->createPage()) {
                renderThemeFile('edit.php');
            }
            break;
        default:
            renderThemeFile('404.php', 404);
            exit;
    }
} else {
    switch ($_GET['action']) {
        case 'delete':
            if ($wiki->deletePage(true)) {
                renderThemeFile('delete.php');
            }
            break;
        case 'deleteOK':
            if ($wiki->deletePage()) {
                redirect($wiki->getLocation());
            }
            break;
        case 'edit':
            if ($wiki->editPage()) {
                renderThemeFile('edit.php');
            }
            break;
        case 'history':
            if ($wiki->history()) {
                renderThemeFile('history.php');
            }
            break;
        case 'restore':
            $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
            if ($version > 0) {
                if ($wiki->revertToVersion($version)) {
                    renderThemeFile('edit.php');
                }
            }
            renderThemeFile('error.php', 400);
            break;
        case 'createPage':
            renderThemeFile('error.php', 400); // can't recreate existing page
            break;
        default:
            if ($wiki->readPage()) {
                renderThemeFile('view.php');
            }
    }
}

// if we got here, then no 'exit' fired - probably a permission error
renderLoginOrDenied();
