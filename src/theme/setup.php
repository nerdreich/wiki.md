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

// --- register theme macros ---------------------------------------------------

$wiki->registerMacro('paginate', function (
    ?string $primary,
    ?array $secondary,
    string $path
): string {
    $snippet = '';
    $pages = [];
    $myIndex = -1;
    $basename = basename($path);

    // load all matching files
    $pattern = '/^' . str_replace('*', '.*', $primary) . '$/';
    foreach (scandir(dirname($path)) as $filename) {
        if (preg_match($pattern, $filename)) {
            if (is_file(dirname($path) . '/' . $filename)) {
                $pages[] = preg_replace('/\.md$/', '', $filename);
                if (basename($filename) === $basename) { // hey - it's us!
                    $myIndex = sizeof($pages) - 1;
                }
            }
        }
    }

    // output pagination
    if ($myIndex < 0) {
        return '{{error prevnext-not-found}}';
    }
    if ($myIndex > 0) {
        $snippet .= '[←](' . ($pages[$myIndex - 1]) . ') | ';
    }
    $snippet .= ___('Page %d of %d', $myIndex + 1, count($pages));
    if ($myIndex < sizeof($pages) - 1) {
        $snippet .= ' | [→](' . ($pages[$myIndex + 1]) . ')';
    }
    return $snippet;
});

// --- other theme helpers -----------------------------------------------------

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
