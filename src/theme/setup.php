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

// --- setup I18N --------------------------------------------------------------

require_once('wiki.i18n.php');
at\nerdreich\i18n\Translate::loadLanguage(dirname(__FILE__) . '/I18N/' . $config['language'] . '.yaml');

function getPageLinksHTML($user, $wiki)
{
    $html = '';
    if ($user->mayUpdate($wiki->getPath())) {
        if ($wiki->exists()) {
            $html .= '<a href="?action=edit">' . ___('Edit') . '</a><br>';
        } else {
            $html .= '<a href="?action=createPage">' . ___('Create') . '</a><br>';
        }
    }
    if ($wiki->exists() && $user->mayRead($wiki->getPath()) && $user->mayUpdate($wiki->getPath())) {
        $html .= '<a href="?action=history">' . ___('History') . '</a><br>';
    }
    if ($wiki->exists() && $user->mayDelete($wiki->getPath())) {
        $html .= '<a href="?action=delete">' . ___('Delete') . '</a><br>';
    }
    if ($user->isLoggedIn()) {
        $html .= '<a href="?auth=logout">' . ___('Logout') . '</a>';
    } else {
        $html .= '<a href="?auth=login">' . ___('Login') . '</a>';
    }
    return $html;
}
