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
    private $actionRoutes = [];
    private $pageRoutes = [];

    /**
     * Constructor
     *
     * Startup wiki for further processing.
     */
    public function __construct($wikiPath = null)
    {
        $root = dirname(dirname(__FILE__));
        // load wiki
        $this->config = parse_ini_file($root . '/data/config.ini');
        $this->user = new UserSession($this->config);
        $this->wiki = new Wiki($this->config, $this->user);

        // determine content path. will trim folder if wiki.md is installed in a sub-folder.
        $wikiPath = $wikiPath ?? substr($this->sanitizePath(
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ), strlen($this->wiki->getWikiRoot()));

        // if there is a better path name for the same resource, redirect there
        $canonicalPath = $this->wiki->init($wikiPath);
        if ($wikiPath !== $canonicalPath) {
            $this->redirect($canonicalPath);
        }

        // set a default route for viewing pages
        $this->registerPageRoute(function ($ui) {
            if (!$ui->wiki->exists()) {
                $ui->renderThemeFile('404', 404);
            }
            if ($ui->wiki->readPage()) {
                $ui->renderThemeFile('view');
            }
            $ui->renderThemeFile('error', 400);
        });

        // load plugins
        foreach (explode(',', $this->config['plugins'] ?? '') as $plugin) {
            $plugin = preg_replace('/[^\w]+/', '', $plugin); // sanitize folder name
            require $root . '/plugins/' . $plugin . '/plugin.php';
        }
        foreach ($GLOBALS['wiki.md-plugins'] as $plugin => $className) {
            $this->wiki->registerPlugin($plugin, new $className($this));
        }

        // setup theme config
        $this->config['themePath'] = $this->wiki->getLocation('/themes/' . $this->config['theme']) . '/';
        $this->config['themeDirFS'] = dirname(__FILE__) . '/../themes/' . $this->config['theme'];
        $this->config['pluginDirFS'] = dirname(__FILE__) . '/../plugins';
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
        $path = preg_replace('/[^\w\s\d\-_~,;\/\[\]\(\)\.]/', '', $path); // only whitelisted chars
        $path = preg_replace('/\.\.+/', '', $path); // no '..'
        $path = preg_replace('/^\/*/', '/', $path); // make sure there is only one leading slash
        return $path;
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
        $action = null;
        $routes = array_keys($this->actionRoutes);
        sort($routes);
        foreach ($routes as $category) {
            if (array_key_exists($category, $_POST)) { // prefer POST over GET
                $action = $this->actionRoutes[$category][$_POST[$category]];
                break;
            } elseif (array_key_exists($category, $_GET)) {
                $action = $this->actionRoutes[$category][$_GET[$category]];
                break;
            }
        }
        if ($action !== null) {
            $action($this);
            $this->renderThemeFile('error', 401); // route didn't exit
            exit;
        }
        // fallback(s)
        foreach ($this->pageRoutes as $action) {
            $action($this);
        }
    }

    public function registerActionRoute(
        string $category,
        string $action,
        callable $handler
    ): void {
        $this->actionRoutes[$category][$action] = $handler;
    }

    public function registerPageRoute(
        callable $handler
    ): void {
        array_unshift($this->pageRoutes, $handler);
    }

    // --- theme file handling -------------------------------------------------

    public function getThemeSetupFile(): string
    {
        return $this->config['themeDirFS'] . '/theme.php';
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
     * Output a plugin file and terminate further execution.
     *
     * This puts the the theme file into a function scope.
     *
     * @param string $filename Theme file to load, e.g. 'edit.php'.
     * @param int $httpResponseCode Code to send this page with to the client.
     */
    public function renderPluginFile(string $pluginname, string $filename, $httpResponseCode = 200): void
    {
        $ui = $this; // to be used in the required theme file
        http_response_code($httpResponseCode);
        require($this->config['pluginDirFS'] . '/' . $pluginname . '/' . $filename . '.php');
        exit;
    }

    /**
     * Output a theme file and terminate further execution.
     *
     * This puts the the theme file into a function scope.
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
