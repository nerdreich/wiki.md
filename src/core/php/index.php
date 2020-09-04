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
$ui = new \at\nerdreich\WikiUI();
require_once $ui->getThemeSetupFile();

// --- register authentication routes ------------------------------------------

$ui->registerActionRoute('auth', 'login', function ($ui) {
    if ($ui->user->login(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
        $ui->redirect($ui->wiki->getLocation(), $ui->getActions()); // successfull -> redirect back
    }
    $ui->renderThemeFile('login', 401); // unsuccessful -> show login again
});

$ui->registerActionRoute('auth', 'logout', function ($ui) {
    $ui->user->logout();
    $ui->redirect($ui->wiki->getLocation(), $ui->getActions());
});

// --- register user management routes -----------------------------------------

$ui->registerActionRoute('user', 'list', function ($ui) {
    if ($ui->user->adminFolder($ui->wiki->getWikiPath()) !== null) {
        $ui->renderThemeFile('admin_folder');
    }
    $ui->renderLoginOrDenied(); // transparent login
});

$ui->registerActionRoute('user', 'delete', function ($ui) {
    if ($ui->user->deleteUser($_GET['name'])) {
        $ui->redirect($ui->wiki->getLocation(), 'user=list');
    }
});

$ui->registerActionRoute('user', 'set', function ($ui) {
    if (
        $ui->user->setPermissions(
            $ui->wiki->getWikiPath(),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userCreate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userRead'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userUpdate'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userDelete'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userMedia'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/,/', preg_replace('/\s+/', '', $_POST['userAdmin'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        )
    ) {
        $ui->redirect($ui->wiki->getLocation(), 'user=list');
    }
});

$ui->registerActionRoute('user', 'secret', function ($ui) {
    if ($ui->user->addSecret($_POST['username'], $_POST['secret'])) {
        $ui->redirect($ui->wiki->getLocation(), 'user=list');
    }
});

// --- register page routes ----------------------------------------------------

$ui->registerActionRoute('page', 'save', function ($ui) {
    $alias = trim(preg_replace('/\s+/', ' ', $_POST['author']));
    if (
        $ui->wiki->savePage(
            trim(str_replace("\r", '', $_POST['content'])),
            trim(preg_replace('/\s+/', ' ', $_POST['title'])),
            $alias
        )
    ) {
        $ui->user->setAlias($alias);
        $ui->redirect($ui->wiki->getLocation());
    };
});

if ($ui->wiki->exists()) {
    // these routes are only added if the item exists
    $ui->registerActionRoute('page', 'edit', function ($ui) {
        if ($ui->wiki->editPage()) {
            $ui->renderThemeFile('edit');
        }
        $ui->renderLoginOrDenied(); // transparent login
    });
    $ui->registerActionRoute('page', 'history', function ($ui) {
        if ($ui->wiki->history()) {
            $ui->renderThemeFile('history');
        }
        $ui->renderLoginOrDenied(); // transparent login
    });
    $ui->registerActionRoute('page', 'restore', function ($ui) {
        $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
        if ($version > 0) {
            if ($ui->wiki->revertToVersion($version)) {
                $ui->renderThemeFile('edit');
            }
        }
    });
    $ui->registerActionRoute('page', 'delete', function ($ui) {
        if ($ui->wiki->deletePage(true)) {
            $ui->renderThemeFile('delete');
        }
    });
    $ui->registerActionRoute('page', 'deleteOK', function ($ui) {
        if ($ui->wiki->deletePage()) {
            $ui->redirect($ui->wiki->getLocation());
        }
    });
} else {
    // these routes are only added if the item does not exist
    $ui->registerActionRoute('page', 'create', function ($ui) {
        if ($ui->wiki->create()) {
            $ui->renderThemeFile('edit');
        }
    });
}

// --- ready! ------------------------------------------------------------------

$ui->run();
