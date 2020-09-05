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

require_once 'core/WikiUI.php';
$wiki = new \at\nerdreich\WikiUI();
require_once $wiki->getThemeSetupFile();

// --- register authentication routes ------------------------------------------

$wiki->registerActionRoute('auth', 'login', function ($wiki) {
    if ($wiki->user->login(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
        $wiki->redirect($wiki->core->getLocation(), $wiki->getActions()); // successfull -> redirect back
    }
    $wiki->renderThemeFile('login', 401); // unsuccessful -> show login again
});

$wiki->registerActionRoute('auth', 'logout', function ($wiki) {
    $wiki->user->logout();
    $wiki->redirect($wiki->core->getLocation(), $wiki->getActions());
});

// --- register user management routes -----------------------------------------

$wiki->registerActionRoute('user', 'list', function ($wiki) {
    if ($wiki->user->adminFolder($wiki->core->getWikiPath()) !== null) {
        $wiki->renderThemeFile('admin_folder');
    }
    $wiki->renderLoginOrDenied(); // transparent login
});

$wiki->registerActionRoute('user', 'delete', function ($wiki) {
    if ($wiki->user->deleteUser($_GET['name'])) {
        $wiki->redirect($wiki->core->getLocation(), 'user=list');
    }
});

$wiki->registerActionRoute('user', 'set', function ($wiki) {
    if (
        $wiki->user->setPermissions(
            $wiki->core->getWikiPath(),
            [
                'pageCreate' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['pageCreate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                'pageRead' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['pageRead'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                'pageUpdate' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['pageUpdate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                'pageDelete' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['pageDelete'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                'mediaAdmin' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['mediaAdmin'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
                'userAdmin' => preg_split('/,/', preg_replace('/\s+/', '', $_POST['userAdmin'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
            ]
        )
    ) {
        $wiki->redirect($wiki->core->getLocation(), 'user=list');
    }
});

$wiki->registerActionRoute('user', 'secret', function ($wiki) {
    if ($wiki->user->addSecret($_POST['username'], $_POST['secret'])) {
        $wiki->redirect($wiki->core->getLocation(), 'user=list');
    }
});

// --- register page routes ----------------------------------------------------

$wiki->registerActionRoute('page', 'save', function ($wiki) {
    $alias = trim(preg_replace('/\s+/', ' ', $_POST['author']));
    if (
        $wiki->core->savePage(
            trim(str_replace("\r", '', $_POST['content'])),
            trim(preg_replace('/\s+/', ' ', $_POST['title'])),
            $alias
        )
    ) {
        $wiki->user->setAlias($alias);
        $wiki->redirect($wiki->core->getLocation());
    };
});

if ($wiki->core->exists()) {
    // these routes are only added if the item exists
    $wiki->registerActionRoute('page', 'edit', function ($wiki) {
        if ($wiki->core->editPage()) {
            $wiki->renderThemeFile('edit');
        }
        $wiki->renderLoginOrDenied(); // transparent login
    });
    $wiki->registerActionRoute('page', 'history', function ($wiki) {
        if ($wiki->core->history()) {
            $wiki->renderThemeFile('history');
        }
        $wiki->renderLoginOrDenied(); // transparent login
    });
    $wiki->registerActionRoute('page', 'restore', function ($wiki) {
        $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
        if ($version > 0) {
            if ($wiki->core->revertToVersion($version)) {
                $wiki->renderThemeFile('edit');
            }
        }
    });
    $wiki->registerActionRoute('page', 'delete', function ($wiki) {
        if ($wiki->core->deletePage(true)) {
            $wiki->renderThemeFile('delete');
        }
    });
    $wiki->registerActionRoute('page', 'deleteOK', function ($wiki) {
        if ($wiki->core->deletePage()) {
            $wiki->redirect($wiki->core->getLocation());
        }
    });
} else {
    // these routes are only added if the item does not exist
    $wiki->registerActionRoute('page', 'create', function ($wiki) {
        if ($wiki->core->create()) {
            $wiki->renderThemeFile('edit');
        }
    });
}

// --- ready! ------------------------------------------------------------------

$wiki->run();
