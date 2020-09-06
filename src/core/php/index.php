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
$wiki = new \at\nerdreich\wiki\WikiUI();
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
