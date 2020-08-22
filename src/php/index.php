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
 */
function renderThemeFile(string $filename): void
{
    global $config, $wiki, $user;
    require($config['themeRoot'] . $filename);
    exit;
}

// --- setup wiki --------------------------------------------------------------

require_once('core/Wiki.php');
require_once('core/UserSession.php');
$user = new at\nerdreich\UserSession($config);
$wiki = new at\nerdreich\Wiki($config, $user);

// --- setup theme --------------------------------------------------------------

$config['themePath'] = $wiki->getPathRoot() . '/themes/' . $config['theme'] . '/';
$config['themeRoot'] = dirname(__FILE__) . '/themes/' . $config['theme'] . '/';
require_once($config['themeRoot'] . 'setup.php');

// --- route requests ----------------------------------------------------------

// determine content path. will trim folder if wiki.md is installed in a sub-folder.
$contentPath = substr(sanitizePath(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
), strlen($wiki->getPathRoot()));

$cannonicalPath = $wiki->init($contentPath);
if ($contentPath != $cannonicalPath) {
    redirect($cannonicalPath);
}

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

// actions that work on existing & non-existing pages
switch ($_GET['action']) {
    case 'save': // saving new pages
        $user->setAlias(trim($_POST['author']));
        if (
            $wiki->savePage(
                trim(str_replace("\r", '', $_POST['content'])),
                trim($_POST['title']),
                trim($user->getAlias())
            )
        ) {
            redirect($wiki->getLocation());
        } else {
            renderThemeFile('403.php');
        };
        break;
}

if (!$wiki->exists()) {
    switch ($_GET['action']) {
        case 'createPage':
            if ($wiki->createPage()) {
                renderThemeFile('edit.php');
            } else {
                renderThemeFile('403.php');
            }
            break;
        default:
            renderThemeFile('404.php');
            exit;
    }
} else {
    switch ($_GET['action']) {
        case 'delete':
            if ($wiki->deletePage(true)) {
                renderThemeFile('delete.php');
            } else {
                renderThemeFile('403.php');
            }
            break;
        case 'deleteOK':
            if ($wiki->deletePage()) {
                redirect($wiki->getLocation());
            } else {
                renderThemeFile('403.php');
            }
            break;
        case 'edit':
            if ($wiki->editPage()) {
                renderThemeFile('edit.php');
            } else {
                renderThemeFile('403.php');
            }
            break;
        case 'history':
            if ($wiki->history()) {
                renderThemeFile('history.php');
            } else {
                renderThemeFile('403.php');
            }
            break;
        case 'restore':
            $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
            if ($version > 0) {
                if ($wiki->revertToVersion($version)) {
                    renderThemeFile('edit.php');
                } else {
                    renderThemeFile('403.php');
                }
            }
            renderThemeFile('error.php');
            break;
        default:
            if ($wiki->readPage()) {
                renderThemeFile('view.php');
            } else {
                renderThemeFile('403.php');
            }
    }
}

// if we got here, then no 'exit' fired - probably a permission error
renderThemeFile('login.php');
