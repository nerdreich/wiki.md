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

namespace at\nerdreich\wiki;

require_once('WikiCore.php');
require_once('UserSession.php');

/**
 * Wiki.md UI handling.
 *
 * This class is responsible to handle HTTP requests, themes and forwarding the
 * raw wiki requests to the core Wiki class.
 */
class WikiUI
{
    public $user;
    public $core;
    private $config;
    private $actionRoutes = [];
    private $pageRoutes = [];
    private $menuItems = [];

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
        $this->user = new UserSession($this->config['datafolder'], $this->config['login_simple']);
        $this->core = new WikiCore($this->config, $this->user);

        // determine content path. will trim folder if wiki.md is installed in a sub-folder.
        $wikiPath = $wikiPath ?? substr($this->sanitizePath(
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ), strlen($this->core->getWikiRoot()));

        // if there is a better path name for the same resource, redirect there
        $canonicalPath = $this->core->init($wikiPath);
        if ($wikiPath !== $canonicalPath) {
            $this->redirect($canonicalPath);
        }

        // set a default route for viewing pages
        $this->registerPageRoute(function ($wiki) {
            if (!$wiki->core->exists()) {
                $wiki->renderThemeFile('404', 404);
            }
            if ($wiki->core->readPage()) {
                $wiki->renderThemeFile('view');
            }
            $wiki->renderThemeFile('error', 400);
        });

        // load plugins
        foreach (explode(',', $this->config['plugins'] ?? '') as $plugin) {
            $plugin = preg_replace('/[^\w]+/', '', $plugin); // sanitize folder name
            require $root . '/plugins/' . $plugin . '/_plugin.php';
        }
        foreach ($GLOBALS['wiki.md-plugins'] as $plugin => $className) {
            $handler = new $className($this, $this->core, $this->user, $this->config);
            $handler->setup();
            $this->core->registerPlugin($plugin, $handler);
        }

        // setup theme config
        $this->config['themePath'] = $this->core->getLocation('/themes/' . $this->config['theme']) . '/';
        $this->config['themeDirFS'] = dirname(__FILE__) . '/../themes/' . $this->config['theme'];
        $this->config['pluginDirFS'] = dirname(__FILE__) . '/../plugins';

        // setup page actions
        $this->setupMenuItems();
    }

    // -------------------------------------------------------------------------
    // --- static helpers ------------------------------------------------------
    // -------------------------------------------------------------------------

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

    /**
     * Get the wiki.md source code repository URL.
     *
     * @return string Link to repo/homepage.
     */
    public static function getRepo(): string
    {
        return '$URL$';
    }

    // -------------------------------------------------------------------------
    // --- request routing -----------------------------------------------------
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // --- menu handling -------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Add a menu item to this page's actions.
     *
     * @param string $action An action=value string.
     * @param string $label A label for the menu item.
     */
    public function addMenuItem(
        string $action,
        string $label
    ): void {
        $this->menuItems[$action] = $label;
    }

    /**
     * Get all registered menu items for this page.
     *
     * @return array $action Aray with 'action=value' as key and label as value.
     */
    public function getMenuItems(): array
    {
        return $this->menuItems;
    }

    /**
     * Determine what the current user may do with the current page.
     *
     * Will update internal $pageActions array.
     */
    public function setupMenuItems(): void
    {
        if ($this->core->mayUpdatePath()) {
            if ($this->core->exists()) {
                $this->addMenuItem('page=edit', 'Edit');
            } else {
                $this->addMenuItem('page=create', 'Create');
            }
        }
        if ($this->core->exists()) {
            if ($this->core->mayReadPath() && $this->core->mayUpdatePath()) {
                $this->addMenuItem('page=history', 'History');
            }
            if ($this->core->mayDeletePath()) {
                $this->addMenuItem('page=delete', 'Delete');
            }
        }
        if ($this->user->isLoggedIn()) {
            $this->addMenuItem('auth=logout', 'Logout');
        } else {
            $this->addMenuItem('auth=login', 'Login');
        }
    }

    // -------------------------------------------------------------------------
    // --- theme file handling -------------------------------------------------
    // -------------------------------------------------------------------------

    public function getThemeSetupFile(): string
    {
        return $this->config['themeDirFS'] . '/_theme.php';
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
    public function renderPluginFile(
        string $pluginname,
        string $filename,
        $httpResponseCode = 200
    ): void {
        $wiki = $this; // to be used in the required theme file
        http_response_code($httpResponseCode);
        $pluginFileFS = $this->config['pluginDirFS'] . '/' . $pluginname . '/' . $filename . '.php';
        require($this->config['themeDirFS'] . '/plugin.php');
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
    public function renderThemeFile(
        string $filename,
        $httpResponseCode = 200
    ): void {
        $wiki = $this; // to be used in the required theme file
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

    public static function echoIf(
        ?string $prefix,
        ?string $content,
        ?string $postfix
    ): void {
        if ($content !== null && $content !== '') {
            echo ($prefix ?? '') . htmlspecialchars($content) . ($postfix ?? '');
        }
    }
}
