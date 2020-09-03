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

namespace at\nerdreich;

require_once('Wiki.php');
require_once('UserSession.php');

/**
 * Wiki.md UI handling.
 *
 * This class is in charge of HTTP requests, handling themes and forwarding the
 * raw wiki requests to the core Wiki class.
 */
class WikiUI
{
    public $user;
    public $wiki;
    private $config;
    private $routes;
    private $routeDefault;

    /**
     * Constructor
     *
     * Startup wiki for further processing.
     */
    public function __construct()
    {
        // general setup
        $this->config = parse_ini_file(dirname(__FILE__) . '/../data/config.ini');
        $this->user = new UserSession($this->config);
        $this->wiki = new Wiki($this->config, $this->user);
        $this->routes = [];
        $this->routeDefault = function ($ui) {
        };

        // determine content path. will trim folder if wiki.md is installed in a sub-folder.
        $contentPath = substr($this->sanitizePath(
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ), strlen($this->wiki->getWikiRoot()));

        // if there is a better path name for the same resource, redirect there
        $canonicalPath = $this->wiki->init($contentPath);
        if ($contentPath !== $canonicalPath) {
            $this->redirect($canonicalPath);
        }

        // setup theme config
        $this->config['themePath'] = $this->wiki->getLocation('/themes/' . $this->config['theme']) . '/';
        $this->config['themeDirFS'] = dirname(__FILE__) . '/../themes/' . $this->config['theme'];
    }

    // --- static helpers ------------------------------------------------------

    /**
     * Terminate further execution and redirect the client to another page.
     *
     * Has to be called before any output is sent to client or setting headers will
     * fail.
     *
     * @param string $path URL path to send user to.
     * @param string $path Wiki action to add to path.
     */
    public static function redirect(
        string $path,
        string $action = ''
    ) {
        $action = $action === '' ? '' : '?' . $action;
        header('Location: ' . $path . $action);
        exit; // terminate execution to enforce redirect
    }

    /**
     * Remove 'bad' stuff from a path so no one can break out of the docroot.
     *
     * @param string $path Path to sanitize.
     * @return string A path with invalid characters and '..' tricks removed.
     */
    public static function sanitizePath(
        string $path
    ): string {
        $path = urldecode($path);
        $path = mb_ereg_replace('([^\w\s\d\-_~,;/\[\]\(\).])', '', $path); // only whitelisted chars
        $path = mb_ereg_replace('([\.]{2,})', '', $path); // no '..'
        $path = mb_ereg_replace('^/*', '/', $path); // make sure there is only one leading slash
        return $path;
    }

    /**
     * Deliver a file, usually an image or uploaded file, to the client.
     *
     * Will set proper HTTP headers and terminate execution after sending the blob.
     *
     * @param string $pathFS Absolute path of file to send.
     */
    public static function renderMedia(string $pathFS): void
    {
        header('Content-Type:' . mime_content_type($pathFS));
        header('Content-Length: ' . filesize($pathFS));
        readfile($pathFS);
        exit;
    }

    /**
     * Assemble page actions for forwarding.
     *
     * @return string get-string containing action(s).
     */
    public static function getActions(): string
    {
        $actions = '';
        $actions .= array_key_exists('user', $_GET) ? '&user=' . urlencode($_GET['user']) : '';
        $actions .= array_key_exists('page', $_GET) ? '&page=' . urlencode($_GET['page']) : '';
        $actions .= array_key_exists('media', $_GET) ? '&media=' . urlencode($_GET['media']) : '';
        return $actions === '' ? $actions : substr($actions, 1);
    }

    // --- request routing -----------------------------------------------------

    public function run(): void
    {
        // execute handler
        $handler = null;
        foreach (array_keys($this->routes) as $action) {
            if (array_key_exists($action, $_POST)) { // prefer POST over GET
                $handler = $this->routes[$action][$_POST[$action]];
                break;
            } elseif (array_key_exists($action, $_GET)) {
                $handler = $this->routes[$action][$_GET[$action]];
                break;
            }
        }
        if ($handler !== null) {
            $handler($this);
            $this->renderThemeFile('error', 401); // route didn't exit
            exit;
        }
        ($this->routeDefault)($this); // fallback
    }

    public function registerRoute(
        string $category,
        string $action,
        callable $handler
    ): void {
        $this->routes[$category][$action] = $handler;
    }

    public function registerRouteDefault(
        callable $handler
    ): void {
        $this->routeDefault = $handler;
    }

    // --- theme file handling -------------------------------------------------

    public function getThemeSetupFile(): string
    {
        return $this->config['themeDirFS'] . '/setup.php';
    }

    public function getThemePath(): string
    {
        return $this->config['themePath'];
    }

    public function getLanguage(): string
    {
        return $this->config['language'];
    }

    public function getDateTimeFormat(): string
    {
        return $this->config['datetime'];
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
    public function renderThemeFile(string $filename, $httpResponseCode = 200): void
    {
        $ui = $this; // to be used in the required theme file
        http_response_code($httpResponseCode);
        require($this->config['themeDirFS'] . '/' . $filename . '.php');
        exit;
    }

    /**
     * Show a permission-denied message for logged-in users or the login screen for
     * logged out users.
     */
    public function renderLoginOrDenied()
    {
        if ($this->user->isLoggedIn()) {
            // as this user is logged in, (s)he just has insufficient permissions
            $this->renderThemeFile('403', 403);
        } else {
            // user has to login-first
            $this->renderThemeFile('login', 401);
        }
    }
}
