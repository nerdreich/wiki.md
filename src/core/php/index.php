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

// --- register a default route ------------------------------------------------

$ui->registerRouteDefault(function ($ui) {
    if ($ui->wiki->isMedia() && $ui->user->mayRead($ui->wiki->getWikiPath())) {
        $ui->renderMedia($ui->wiki->getContentFileFS());
    }
    if (!$ui->wiki->exists()) {
        $ui->renderThemeFile('404', 404);
    }
    if ($ui->wiki->readPage()) {
        $ui->renderThemeFile('view');
    }
});

// --- register authentication routes ------------------------------------------

$ui->registerRoute('auth', 'login', function ($ui) {
    if ($ui->user->login(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
        $ui->redirect($ui->wiki->getLocation(), $ui->getActions()); // successfull -> redirect back
    }
    $ui->renderThemeFile('login', 401); // unsuccessful -> show login again
});

$ui->registerRoute('auth', 'logout', function ($ui) {
    $ui->user->logout();
    $ui->redirect($ui->wiki->getLocation(), $ui->getActions());
});

// --- register user management routes -----------------------------------------

$ui->registerRoute('user', 'list', function ($ui) {
    if ($ui->user->adminFolder($ui->wiki->getWikiPath()) !== null) {
        $ui->renderThemeFile('admin_folder');
    }
    $ui->renderLoginOrDenied(); // transparent login
});

$ui->registerRoute('user', 'delete', function ($ui) {
    if ($ui->user->deleteUser($_GET['name'])) {
        $ui->redirect($ui->wiki->getLocation(), 'user=list');
    }
});

$ui->registerRoute('user', 'set', function ($ui) {
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

$ui->registerRoute('user', 'secret', function ($ui) {
    if ($ui->user->addSecret($_POST['username'], $_POST['secret'])) {
        $ui->redirect($ui->wiki->getLocation(), 'user=list');
    }
});

// --- register media routes ---------------------------------------------------

$ui->registerRoute('media', 'list', function ($ui) {
    if ($ui->wiki->media($ui->wiki->getWikiPath()) !== null) {
        $ui->renderThemeFile('media');
    }
    $ui->renderLoginOrDenied(); // transparent login
});

$ui->registerRoute('media', 'upload', function ($ui) {
    if ($_FILES['wikimedia'] && $_FILES['wikimedia']['error'] === UPLOAD_ERR_OK) {
        if (
            $ui->wiki->mediaUpload(
                $_FILES['wikimedia']['tmp_name'],
                strtolower(trim($_FILES['wikimedia']['name'])),
                $ui->wiki->getWikiPath()
            )
        ) {
            $ui->redirect($ui->wiki->getLocation(), 'media=list');
        }
    } else { // upload failed, probably due empty selector on submit
        $ui->redirect($ui->wiki->getLocation(), 'media=list');
    }
});

$ui->registerRoute('media', 'delete', function ($ui) {
    if ($ui->wiki->mediaDelete($ui->wiki->getWikiPath()) !== null) {
        $ui->redirect(dirname($ui->wiki->getLocation()) . '/', 'media=list');
    }
});

// --- register page routes ----------------------------------------------------

$ui->registerRoute('page', 'save', function ($ui) {
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
    $ui->registerRoute('page', 'edit', function ($ui) {
        if ($ui->wiki->editPage()) {
            $ui->renderThemeFile('edit');
        }
        $ui->renderLoginOrDenied(); // transparent login
    });
    $ui->registerRoute('page', 'history', function ($ui) {
        if ($ui->wiki->history()) {
            $ui->renderThemeFile('history');
        }
        $ui->renderLoginOrDenied(); // transparent login
    });
    $ui->registerRoute('page', 'restore', function ($ui) {
        $version = (int) preg_replace('/[^0-9]/', '', $_GET['version']);
        if ($version > 0) {
            if ($ui->wiki->revertToVersion($version)) {
                $ui->renderThemeFile('edit');
            }
        }
    });
    $ui->registerRoute('page', 'delete', function ($ui) {
        if ($ui->wiki->deletePage(true)) {
            $ui->renderThemeFile('delete');
        }
    });
    $ui->registerRoute('page', 'deleteOK', function ($ui) {
        if ($ui->wiki->deletePage()) {
            $ui->redirect($ui->wiki->getLocation());
        }
    });
} else {
    // these routes are only added if the item does not exist
    $ui->registerRoute('page', 'create', function ($ui) {
        if ($ui->wiki->create()) {
            $ui->renderThemeFile('edit');
        }
    });
}

// --- ready! ------------------------------------------------------------------

$ui->run();
