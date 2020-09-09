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

require_once('UDiff.php');              // simple file-diff implementation
require_once('lib/spyc.php');           // yaml parser
require_once('lib/Parsedown.php');      // markdown parser
require_once('lib/ParsedownExtra.php'); // better markdown parser

/**
 * Core wiki document handling.
 *
 * This class will do path/file conversion, read/write pages from/to the FS and
 * handle markup conversion.
 */
class WikiCore
{
    private $config = [];
    private $user;

    private $contentDirFS = '';    // e.g. /var/www/www.mysite.com/mywiki/data/content
    private $contentFileFS = 'na'; // e.g. /var/www/www.mysite.com/mywiki/data/content/animal/lion.md
    private $wikiRoot = '';        // url-parent-folder of the wiki, e.g. /mywiki
    private $wikiPath = '/';       // current document path within the wiki, e.g. /animal/lion

    private $metadata = [];        // yaml front matter for a content
    private $content = '';         // the markdown body for a content

    private $filters = [];
    private $plugins = [];

    /**
     * Constructor
     *
     * @param array $config Array with loaded config.ini as key => value entries.
     */
    public function __construct(
        array $config,
        UserSession $user
    ) {
        $this->config = $config;
        $this->user = $user;

        // wiki path + files
        $wikiDirFS = dirname(dirname(__FILE__)); // WikiCore.php is in the ../core folder
        $this->contentDirFS = $wikiDirFS . '/data/content';
        $this->wikiRoot = substr($wikiDirFS, strlen($_SERVER['DOCUMENT_ROOT']));

        // register core filters
        $this->registerFilterFixLinks();
        $this->registerFilterIndentHeadlines();
        $this->registerFilterBrokenLinks();
    }

    /**
     * Prepare processing of a page.
     *
     * Will also detect invalid/hidden dir/README combinations.
     *
     * @param string $wikiPath The document path for the wiki page to load/process.
     * @return string Cannonical path to redirect to.
     */
    public function init(
        string $wikiPath
    ): string {
        // hide /dir/README.md behind /dir/
        if (preg_match('/README$/', $wikiPath)) {
            return dirname($wikiPath) . '/';
        }

        // assemble absolute fs url
        $this->wikiPath = $wikiPath;
        $this->contentFileFS = $this->wikiPathToContentFileFS($this->wikiPath);

        // if this is both a file and a folder, redirect to the folder instead
        if (is_dir(preg_replace('/\.md$/', '/', $this->contentFileFS))) {
            return $wikiPath . '/';
        }

        return $wikiPath;
    }

    /**
     * Get the wiki.md version.
     *
     * @return string SemVer version, e.g. '1.0.2'.
     */
    public function getVersion(): string
    {
        return '$VERSION$';
    }

    // -------------------------------------------------------------------------
    // --- various paths & converters ------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Get the full filesystem path to the wiki's content directory.
     *
     * @return string URI Path, e.g. '/var/www/www.mysite.com/mywiki/data/content'.
     */
    public function getContentDirFS(): string
    {
        return $this->contentDirFS;
    }

    /**
     * Get the full filesystem path to the current item.
     *
     * @return string URI Path, e.g. '/var/www/www.mysite.com/mywiki/data/content/animal/lion.md'.
     */
    public function getContentFileFS(): string
    {
        return $this->contentFileFS;
    }

    /**
     * Get the user-visible path for the current wiki page.
     *
     * In case wiki.md was installed in a sub-directory, this path does not
     * contain it.
     *
     * @return string URI Path, e.g. '/animal/lion'.
     */
    public function getWikiPath(): string
    {
        return $this->wikiPath;
    }

    /**
     * Get the user-visible folder for a wikiPath.
     *
     * In case wiki.md was installed in a sub-directory, the returned path does not
     * contain that.
     *
     * @param string $wikiPath WikiPath to find the folder for. If null (default)
     *               the current page will be used.
     * @return string URI Path, e.g. '/animal/'.
     */
    public function getWikiPathParentFolder(string $wikiPath = null): string
    {
        $wikiPath = $wikiPath ?? $this->wikiPath;
        if ($wikiPath[-1] === '/') { // README / it's own folder
            return $wikiPath;
        } else {
            $dir = dirname($wikiPath);
            return $dir === '/' ? $dir : $dir . '/'; // auto-append slash
        }
    }

    /**
     * Get the parent directory of the wiki.
     *
     * @return string URL Path, e.g. '/mywiki'.
     */
    public function getWikiRoot(): string
    {
        return $this->wikiRoot;
    }

    /**
     * Get the full URL path including potential parent folders.
     *
     * @param string (Optional) WikiPath. If non is provided, the current page
     *               is used.
     * @return string URL Path, e.g. '/mywiki/animal/lion'.
     */
    public function getLocation(string $wikiPath = null): string
    {
        $wikiPath = $wikiPath ?? $this->wikiPath;
        return $this->wikiRoot . $wikiPath;
    }

    /**
     * Resolve an (partly) absolute or relative wikiPath in relation to the
     * current wiki path into an absolute wikiPath.
     *
     * If wiki.md was installed in a subfolder, the resolved path will be absolute
     * within that subfolder, but not contain the subfolder.
     *
     * @param string $path Path to convert, e.g. `animal/../rock/granite`.
     * @return string Resolved path, e.g. `/rock/granite`. Null if invalid (e.g.
     *                outside root).
     */
    public function canonicalWikiPath(string $wikiPath): ?string
    {
        $absPath = preg_replace('/\/$/', '/.', $wikiPath); // treat folder as dot-file
        if ($absPath[0] === '/') {
            // absolute path
            return $this->realpath($absPath);
        } else {
            // (probably) relative path
            return $this->realpath(dirname($this->wikiPath) . '/' . $absPath);
        }
    }

    /**
     * Map URL path to markdown file.
     *
     * - /path/to/folder/ -> /path/to/folder/README.md
     * - /path/to/item > /path/to/item.md
     *
     * @param string $wikiPath Path to lookup.
     * @return mixed Path (string) to file or FALSE if not found.
     */
    public function wikiPathToContentFileFS(
        string $wikiPath
    ): string {
        if ($wikiPath[-1] === '/') { // folder
            $postfix = 'README.md';
        } else { // page
            $postfix = '.md';
        }
        return $this->contentDirFS . $wikiPath . $postfix;
    }

    /**
     * Map URL path to directory of a page.
     *
     * - /path/to/folder/ -> /path/to/
     * - /path/to/item > /path/to/
     *
     * @param string $wikiPath Path to lookup.
     * @return mixed Path (string) to directory or FALSE if not found.
     */
    public function wikiPathToContentDirFS(
        string $wikiPath
    ): string {
        return dirname($this->wikiPathToContentFileFS($wikiPath)) . '/';
    }

    /**
     * Find the wiki-path of a content filename. E.g.
     *
     * /var/www/content/path/to/page.md -> /path/to/page
     * /var/www/content/path/to -> /path/to
     * /var/www/content/page.md -> /
     *
     * @param string $filename Filename to lookup.
     * @return string Wiki path.
     */
    public function contentFileFSToWikiPath(
        string $filename
    ): string {
        $path = substr($filename, strlen($this->contentDirFS));
        $path = preg_replace('/.md$/', '', $path);
        $path = preg_replace('/README$/', '', $path);
        return $path;
    }

    /**
     * Convert potentially relative path to abolute path.
     *
     * Files do not have to actually exist for this to work.
     *
     * @param string $filename Path to convert.
     * @return string Path with all ./.. removed/resolved.
     */
    protected function realpath(string $filename): ?string
    {
        // $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/lion/']));

        if ($filename === '') {
            $filename = '.';
        }
        if ($filename[0] === '/') { // do we have to keep a leading slash?
            $prefix = '/';
        } else {
            $prefix = '';
        }
        if ($filename[-1] === '/') { // do we have to keep a trailing slash?
            $postfix = '/';
        } elseif ($filename[-1] === '.' && strpos($filename, '/') !== false) { // do we have to add a trailing slash?
            $postfix = '/';
        } else {
            $postfix = '';
        }

        $path = [];
        foreach (explode('/', $filename) as $part) {
            if (empty($part) || $part === '.') { // ignore parts that have no value
                continue;
            } elseif ($part !== '..') { // valid part
                array_push($path, $part);
            } elseif (count($path) > 0) { // going up via '..'
                array_pop($path);
            } else { // can't go beyond root
                return null;
            }
        }
        if (count($path) <= 0 && $prefix === $postfix) {
            return $prefix; // avoid double slash
        }
        return $prefix . join('/', $path) . $postfix;
    }

    // -------------------------------------------------------------------------
    // --- permissions ---------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Check if the current user may read/view a path.
     *
     * @param string $path The path to check the permission for. Defaults to current wikiPath.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayCreatePath(
        ?string $path = null
    ): bool {
        return $this->user->hasPermission('pageCreate', $path ?? $this->wikiPath);
    }

    /**
     * Check if the current user may edit/create a path.
     *
     * @param string $path The path to check the permission for. Defaults to current wikiPath.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayReadPath(
        ?string $path = null
    ): bool {
        return $this->user->hasPermission('pageRead', $path ?? $this->wikiPath);
    }

    /**
     * Check if the current user may read/view a path.
     *
     * @param string $path The path to check the permission for. Defaults to current wikiPath.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayUpdatePath(
        ?string $path = null
    ): bool {
        return $this->user->hasPermission('pageUpdate', $path ?? $this->wikiPath);
    }

    /**
     * Check if the current user may edit/create a path.
     *
     * @param string $path The path to check the permission for. Defaults to current wikiPath.
     * @return boolean True, if permissions are sufficient. False otherwise.
     */
    public function mayDeletePath(
        ?string $path = null
    ): bool {
        return $this->user->hasPermission('pageDelete', $path ?? $this->wikiPath);
    }

    // -------------------------------------------------------------------------
    // --- content access for theme/plugin files -------------------------------
    // -------------------------------------------------------------------------

    /**
     * Check if a wiki page exists.
     *
     * @param $wikiPath WikiPath to check. If none/null, the currently loaded
     *                  page is checked.
     * @return boolean True, if the current path matches a page. False if not.
     */
    public function exists(string $wikiPath = null): bool
    {
        if ($wikiPath === null) {
            return is_file($this->contentFileFS);
        } else {
            $wikiPath = $this->canonicalWikiPath($wikiPath);
            return $wikiPath === null ? false : is_file($this->wikiPathToContentFileFS($wikiPath));
        }
    }

    /**
     * Get the tile of the current wiki page.
     *
     * @return string Page title, e.g. 'Lion'.
     */
    public function getTitle(): string
    {
        if (array_key_exists('title', $this->metadata) && strlen($this->metadata['title']) > 0) {
            return $this->metadata['title'];
        }
        return '';
    }

    /**
     * Get the description of the current wiki page.
     *
     * Currently only a dummy implementation that returns the page title instead.
     *
     * @return string Page description e.g. for HTML metadata.
     */
    public function getDescription(): string
    {
        return $this->getTitle();
    }

    /**
     * Get the author of the current wiki page.
     *
     * @return string Author name, e.g. 'Yuki'.
     */
    public function getAuthor(): string
    {
        return $this->metadata['author'];
    }

    /**
     * Get the last-changed date of the current wiki page.
     *
     * @return string Date string in UTC / 'd.m.Y H:i' format.
     */
    public function getDate(): ?\DateTime
    {
        if (array_key_exists('date', $this->metadata)) {
            return \DateTime::createFromFormat(\DateTimeInterface::ATOM, $this->metadata['date']);
        }
        return null;
    }

    /**
     * Get the current wiki page's history.
     *
     * @return Array History.
     */
    public function getHistory(): ?array
    {
        return $this->metadata['history'];
    }

    /**
     * Get the HTML content for the current wiki page.
     *
     * @return string HTML snippet for the content part of this page.
     */
    public function getContentHTML(): string
    {
        return $this->markupToHTML($this->content, $this->contentFileFS);
    }

    /**
     * Get the raw Markdown markup for the current wiki page.
     *
     * @return string The markup for this wiki page.
     */
    public function getContentMarkup(): string
    {
        return $this->content;
    }

    /**
     * Get the HTML for a snippet.
     *
     * Will look for a file matching the snippet name in the current wiki
     * page's folder and, if not found there, recurse up the tree.
     *
     * @param string $snippet Snippet name.
     * @return string Rendered HTML for the closest file '_$snippetName.md' found.
     */
    public function getSnippetHTML(
        string $snippetName
    ): string {
        // fetch html for snippet
        $filename = $this->contentDirFS . $this->findSnippetPath("_$snippetName.md");
        list($metadata, $content) = $this->loadFile($filename);
        $html = $this->markupToHTML($content, $filename);

        // convert headlines to look-alike divs
        $html = preg_replace('/<h([1-6])>/', '<div class="h$1">', $html);
        $html = preg_replace('/<\/h[1-6]>/', '</div>', $html);

        return $html;
    }

    /**
     * Convert markup to HTML.
     *
     * Will apply all markup and html filters.
     *
     * @param string $markup The raw markup to render.
     * @param string $pathFS The absolute path to the content (e.g. for macros).
     * @return string HTML version.
     */
    public function markupToHTML(
        string $markup,
        string $pathFS
    ): string {
        $markup = $this->runFilters('raw', $markup, $pathFS);
        $markup = $this->runFilters('markup', $markup, $pathFS);
        $html = $this->markdown2Html($markup);
        $html = $this->runFilters('html', $html, $pathFS);
        return $html;
    }

    /**
     * Try to revert the current wiki page to an earlier version.
     *
     * Will update class data to reflect this version. Will silently fail if
     * the given version number does not exist.
     *
     * @param int $version A version to revert to (e.g. 2). 1-based index.
     * @return string True if version could be applied.
     */
    public function revertToVersion(
        int $version
    ): bool {
        if ($this->mayReadPath() && $this->mayUpdatePath()) {
            $this->load();

            if ($this->isDirty()) {
                // direct changes in the FS prevent the history from working
                return false;
            }

            $historySize = count($this->metadata['history'] ?? []);
            if ($version > 0 && $version <= $historySize) {
                // reverse-apply all diffs up to to the requested version
                for ($revertTo = $historySize; $revertTo >= $version; $revertTo--) {
                    $diffToApply = $revertTo - 1;
                    $diff = $this->metadata['history'][$diffToApply]['diff'];
                    if ($diff !== null) {
                        $this->applyEncodedDiffToContent($this->metadata['history'][$diffToApply]['diff']);
                    } else {
                        return false; // broken history, probably due to fs-change. we can't go back from here.
                    }
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Decode the given diff and apply it to the content.
     *
     * @param string $encodedDiff Diff to apply.
     */
    private function applyEncodedDiffToContent(string $encodedDiff): void
    {
        if ($encodedDiff !== null) {
            $diff = gzuncompress(base64_decode($encodedDiff));
            $this->content = \at\nerdreich\wiki\UDiff::patch($this->content, $diff, true);
        }
    }

    /**
     * Undo one / the last version of this page.
     *
     * Will update class data to reflect this version. Will silently fail if
     * the given version number does not exist.
     */
    private function revertToPreviousVersion(): void
    {
        $this->content = ''; // without history the previous version was empty
        if (array_key_exists('history', $this->metadata)) {
            $count = count($this->metadata['history']);
            if ($count > 0) {
                $this->applyEncodedDiffToContent($this->metadata['history'][$count - 1]['diff']);
            }
        }
    }

    // -------------------------------------------------------------------------
    // --- plugin handling  ----------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Add/register a plugin.
     *
     * @param string $name Name of plugin, e.g. 'media'.
     * @param object $plugin The plugin.
     */
    public function registerPlugin(
        string $name,
        object $plugin
    ): void {
        $this->plugins[$name] = $plugin;
    }

    /**
     * Access a registered/loaded plugin.
     *
     * @param string $name Name of plugin, e.g. 'media'.
     * @return object The plugin.
     */
    public function getPlugin(
        string $name
    ): ?object {
        return $this->plugins[$name];
    }

    // -------------------------------------------------------------------------
    // --- filter handling  ----------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Add a filter to be executed during content delivery.
     *
     * @param string $hook When this filter should be run ('markup' or 'html').
     * @param string $filterName Name of this filter.
     * @param callable $handler A function (string, string): string filter.
     */
    public function registerFilter(
        string $hook,
        string $filterName,
        callable $handler
    ): void {
        $this->filters[$hook][$filterName] = $handler;
    }

    /**
     * Improve markdown for rendering.
     *
     * Will apply all markup postprocess filters.
     *
     * @param string $markdown The Markdown content to apply the filters to.
     * @param string $fsPath The absolute fs-path to the content.
     * @return string Updated content.
     */
    private function runFilters(
        string $hook,
        string $markdown,
        string $fsPath
    ): string {
        foreach ($this->filters[$hook] ?? [] as $filter) {
            $markdown = $filter($markdown, $fsPath);
        }
        return $markdown;
    }

    /**
     * Filter: Convert relative to absolute links in Markdown content.
     *
     * Particularly usefull to fix snippets, which contain links relative to
     * the snippet location, not to the page location that includes them.
     */
    private function registerFilterFixLinks(): void
    {
        $this->registerFilter('markup', 'markdownFixLinks', function (string $markdown, string $fsPath): string {
            $folder = $this->getWikiPathParentFolder($this->contentFileFSToWikiPath($fsPath));

            // add absolute path to all relative links
            preg_match_all('/\[([^]]*)\]\(([^)]*)\)/', $markdown, $matches);
            list($matchFull, $matchText, $matchLink) = $matches;
            for ($index = 0; $index < count($matchLink); $index++) {
                if ($matchLink[$index][0] === '/') { // skip absolute urls and //-urls
                    continue;
                }
                if (preg_match('/^https?:/', $matchLink[$index])) { // skip http[s]: links
                    continue;
                }
                $markdown = str_replace($matchFull[$index], '[' . $matchText[$index] . ']('
                    . $this->getLocation($folder . $matchLink[$index]) . ')', $markdown);
            }
            return $markdown;
        });
    }

    /**
     * Filter: Mark internal links to non-exisiting pages 'broken'.
     *
     * For performance reasons, this will only run when a user is logged-in.
     */
    private function registerFilterBrokenLinks(): void
    {
        $this->registerFilter('markup', 'markdownDeadLinks', function (string $markdown, string $fsPath): string {
            if ($this->user->isLoggedIn()) {
                $folder = $this->getWikiPathParentFolder($this->contentFileFSToWikiPath($fsPath));

                $markdown = preg_replace_callback('/\[([^]]*)\]\(([^)]*)\)/', function ($matches) {
                    list($matchFull, $matchText, $matchLink) = $matches;
                    if (preg_match('/^https?:/', $matchLink)) { // skip http[s]: links
                        return $matchFull;
                    }

                    // fixLinks filter added wikiroot - need to remove it again for check
                    $wikiRootLength = strlen($this->wikiRoot);
                    if (!$this->exists(substr($matchLink, $wikiRootLength))) {
                        return $matchFull . '{.broken}';
                    }
                    return $matchFull;
                }, $markdown);
            }
            return $markdown;
        });
    }

    /**
     * Filter: Make all headlines one level deeper.
     *
     * This is neede as the page will get a <h1> based on the page title.
     */
    private function registerFilterIndentHeadlines(): void
    {
        $this->registerFilter('markup', 'markdownIndentHeadlines', function (string $markdown, string $fsPath): string {
            return preg_replace('/^#/m', '##', $markdown);
        });
    }

    // -------------------------------------------------------------------------
    // --- file handling  ------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Read a file's content.
     *
     * Will take care of file locking.
     *
     * @param $filename Path to file to read.
     * @return Content, nor null if file could not be read.
     */
    private function readContentFS(
        string $filename
    ): string {
        $contents = null;

        if (is_file($filename) && ($handle = @fopen($filename, 'r')) !== false) {
            if (flock($handle, LOCK_SH)) {
                $contents = @file_get_contents($filename);
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }

        return $contents;
    }

    /**
     * Write a file's content.
     *
     * Will take care of file locking and implicitly create file.
     *
     * @param $filename Path to file to read.
     * @param $content Data to write into file.
     * @return Content, nor null if file could not be read.
     */
    private function writeContentFS(
        string $filename,
        string $content
    ): bool {
        return file_put_contents($filename, $content, LOCK_EX) !== false;
    }

    // -------------------------------------------------------------------------
    // --- page management -----------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Determine if the file has been changed on disk.
     *
     * wiki.md assumes that only this class makes changes to .md files. If
     * someone else does, the page content hash will no longer match and the
     * page history will no longer work. (To correct this, the page just has
     * to be re-saved and the hash re-calculated.)
     *
     * @return bool True, if content in filesystem does not match with our hash.
     */
    public function isDirty(): bool
    {
        if (array_key_exists('hash', $this->metadata)) {
            $hash = hash('sha1', $this->content);
            return $hash !== $this->metadata['hash'];
        }
        return true;
    }

    /**
     * Determine if the file is being edited (work in progress) by someone else.
     *
     * @return int 0 if this ist not a Wip or seconds since edit started.
     */
    public function isWip(): int
    {
        if ($this->metadata['editBy'] !== $this->user->getSessionToken()) { // we don't care about our own session
            if (array_key_exists('edit', $this->metadata)) {
                $lastEditDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $this->metadata['edit']);
                $deltaSeconds = (new \DateTime())->getTimestamp() - $lastEditDate->getTimestamp();
                if ($deltaSeconds < $this->config['edit_warning_interval'] ?? -1) {
                    return $deltaSeconds;
                }
            }
        }
        return 0;
    }

    /**
     * Prepare to edit a page.
     *
     * Won't save any content, but if page already exists, it will mark it as
     * being edited.
     *
     * @return bool True, if the user may continue to edit this page.
     */
    public function editPage(): bool
    {
        if ($this->mayReadPath() && $this->mayUpdatePath()) {
            if ($this->exists()) {
                $this->load();
                if (!array_key_exists('edit', $this->metadata)) {
                    $this->metadata['edit'] = date(\DateTimeInterface::ATOM); // mark wip
                    $this->metadata['editBy'] = $this->user->getSessionToken();
                    $this->persist(true);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Remove last history item if it was done by the same author in a short time.
     *
     * @param string $author Author to check against.
     * @return bool True if successfull (squashed or not), false if an error occured.
     */
    private function squashLastHistoryItem(
        string $author
    ): bool {
        if ($this->metadata['author'] === $author) {
            if (array_key_exists('date', $this->metadata)) {
                $lastSaveDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $this->metadata['date']);
                $deltaSeconds = (new \DateTime())->getTimestamp() - $lastSaveDate->getTimestamp();
                if ($deltaSeconds < $this->config['autosquash_interval'] ?? -1) {
                    // this is a quick (re)save. undo last history to merge the saves into one.
                    $this->revertToPreviousVersion();
                    if ($this->metadata['history'] === []) {
                        unset($this->metadata['history']);
                    } else {
                        array_pop($this->metadata['history']);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Put new content in this page.
     *
     * Will also set all corresponding meta fields: hash + diffs.
     *
     * @param string $newContent New content blob.
     * @param bool $updateMetadata True (default), if meta fields should be populated.
     */
    private function setContent(
        string $newContent,
        bool $updateMetadata = true
    ) {
        $diff = \at\nerdreich\wiki\UDiff::diff($this->content, $newContent);
        $this->content = $newContent;

        if ($updateMetadata) {
            $this->metadata['hash'] = hash('sha1', $this->content);

            if (array_key_exists('history', $this->metadata)) {
                // page has a history -> add to that

                if ($diff !== null) { // ignore non-changing saves
                    // create a new history entry
                    $historyEntry = [];
                    $historyEntry['author'] = $this->metadata['author'] ?? '???';
                    $historyEntry['date'] =
                        $this->metadata['date'] ?? date(\DateTimeInterface::ATOM, filemtime($this->contentFileFS));
                    $diff = preg_replace('/^.+\n/', '', $diff); // remove first line (---)
                    $diff = preg_replace('/^.+\n/', '', $diff); // remove second line (+++)
                    $historyEntry['diff'] = chunk_split(base64_encode(gzcompress($diff, 9)), 64, "\n");

                    $this->metadata['history'][] = $historyEntry;
                }
            } else {
                // no history exists -> this is the first save, just start a new/empty one
                $this->metadata['history'] = [];
            }
        }
    }

    /**
     * Correct dirty pages by adding history entries to represent external edits.
     */
    private function fixDirtyPage()
    {
        if ($this->exists()) { // 2nd+ save
            if ($this->metadata['history'] === null) { // no history = legacy .md file
                // start a new history
                $this->metadata['history'] = [];
            } else { // regular wiki.md file
                // add an interim history entry to represent the unknown edit
                $historyEntry = [];
                $historyEntry['author'] = $this->metadata['author'];
                $historyEntry['date'] = $this->metadata['date'];
                $this->metadata['history'][] = $historyEntry;
            }
            $this->metadata['date'] = date(\DateTimeInterface::ATOM, filemtime($this->contentFileFS));
            $this->metadata['author'] = '???';
            $this->metadata['title'] = '';
            $this->metadata['hash'] = hash('sha1', $this->content);
            $this->persist();
            $this->load();
        }
    }

    /**
     * Save a page. Create a new diff/history on the fly if it already existed.
     *
     * @param string $content New markdown content for this page.
     * @param string $title New title of this page.
     * @param string $author Name to store as author for this change.
     * @return bool False if permissions are denied.
     */
    public function savePage(
        string $content,
        string $title,
        string $author
    ): bool {
        if ($this->mayUpdatePath($this->wikiPath)) {
            $this->load();

            // did someone edited the .md file directly in the filesystem?
            if ($this->isDirty()) {
                $this->fixDirtyPage();
            }

            // check if this is yet another quick save by the same author
            if (!$this->squashLastHistoryItem($author)) {
                return false;
            }

            // update content, history & hash
            $this->setContent($content);

            // update other metadata
            $this->metadata['date'] = date(\DateTimeInterface::ATOM);
            $this->metadata['title'] = $title;
            $this->metadata['author'] = $author !== '' ? $author : '???';
            unset($this->metadata['edit']);
            unset($this->metadata['editBy']);

            $this->persist();
            $this->addToChangelog();

            return true;
        } else {
            return false;
        }
    }

    /**
     * Write the current page back to file.
     *
     * @param bool keepmtime Keep the modification time intact.
     */
    private function persist(bool $keepmtime = false): void
    {
        // create parent dir if necessary
        if (!\file_exists(dirname($this->contentFileFS))) {
            mkdir(dirname($this->contentFileFS), 0777, true);
        }

        // write out new content
        $mtime = $keepmtime && $this->exists() ? filemtime($this->contentFileFS) : time();
        $this->writeContentFS(
            $this->contentFileFS,
            \Spyc::YAMLDump($this->metadata) . "---\n" . trim($this->content) . "\n"
        );
        touch($this->contentFileFS, $mtime);
    }

    /**
     * Load the content for this page from the filesystem.
     */
    private function load(): void
    {
        list($this->metadata, $this->content) = $this->loadFile($this->contentFileFS, true);
    }

    /**
     * Create a new, empty page.
     *
     * Will only reset internal data to a blank page, to be picked up by the
     * editor.
     *
     * @return True if page was created. False if user is not allowed.
     */
    public function create(): bool
    {
        if ($this->mayCreatePath($this->wikiPath)) {
            // reset internal data to empty page
            $this->metadata = [];
            $this->metadata['date'] = date(\DateTimeInterface::ATOM);
            $this->content = '';
            $this->contentFileFS = $this->wikiPathToContentFileFS($this->wikiPath);

            // prefill title
            $this->metadata['title'] = str_replace('_', ' ', basename($this->wikiPath)); // underscore to spaces
            $this->metadata['title'] = preg_replace(
                '/([a-z])([A-Z])/',
                '$1 $2',
                $this->metadata['title']
            ); // unCamelCase

            return true;
        }

        return false;
    }

    /**
     * Delete the current wiki page.
     *
     * Will rename the markdownfile to '.deleted', making it invisible to the
     * wiki.
     *
     * @param dryRun If true, all checks are made but the file is not deleted.
     * @return True if page was deleted. False if user is not allowed.
     */
    public function deletePage(bool $dryRun = false): bool
    {
        if ($this->mayDeletePath($this->wikiPath)) {
            $this->load();
            if ($this->exists()) {
                if (!$dryRun) {
                    rename(
                        $this->contentFileFS,
                        $this->contentFileFS . '.deleted'
                    );
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare page for history viewing.
     *
     * @return True if history is ready, false if user is not allowed.
     */
    public function history(): bool
    {
        // does currently not more than load the page
        return $this->readPage();
    }

    /**
     * Prepare page for viewing.
     *
     * @return True if history is ready, false if user is not allowed.
     */
    public function readPage(): bool
    {
        if ($this->mayReadPath()) {
            $this->load();
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // --- Content: Markdown & Meta --------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Extract the YAML front matter from a file's content.
     *
     * @param string $content A file's raw content.
     * @return array Parsed YAML front matter (YFM) as array. Empty if no
     *               YFM was found.
     */
    private function extractYaml(
        string $content
    ): array {
        if (substr($content, 0, 3) !== '---') {
            return []; // seems not to have/start with yaml front matter
        }
        $endIndex = strpos($content, "\n---", 4);
        if ($endIndex === false) {
            return []; // invalid yaml front matter - no end found
        }
        $yaml = substr($content, 4, $endIndex - 4);
        return \Spyc::YAMLLoadString($yaml);
    }

    /**
     * Improve metadata by adding more content information.
     *
     * @param array $yaml Metadata-array.
     * @return array Improved array with more meta data.
     */
    private function enrichYaml(
        array $yaml
    ): array {
        // use path name as title fallback
        $yaml['title'] = $yaml['title'] ?? end(explode('/', $yaml['path']));
        return $yaml;
    }

    /**
     * Render raw Markdown content to HTML.
     *
     * @param string $markup Markdown content.
     * @return string HTML.
     */
    private function markdown2Html(
        string $markup
    ): string {
        $parser = new \ParsedownExtra();
        return $parser->text($markup);
    }

    /**
     * Extract the markup part from a file's content.
     *
     * This just skips an (optional) YAML front matter part.
     *
     * @param string $content A file's raw content.
     * @return string Markup part of this content.
     */
    private function extractMarkup(
        string $content
    ): string {
        $skip = 0;
        if (substr($content, 0, 3) === '---') {
            $skip = strpos($content, "\n---", 3);
            $skip = $skip < 0 ? 0 : $skip + 4;
        }
        return trim(substr($content, $skip));
    }

    /**
     * Load a content file.
     *
     * @param string $filename File to load.
     * @param bool $enrich Optional. If true, the metadata is enriched by
     *                        more content data, e.g. titles, ...
     * @return array Array containing loaded metadata ([0]) and content ([1]).
     */
    public function loadFile(
        string $filename,
        bool $enrich = false
    ): array {
        // load data
        if ($filename !== false && is_file($filename)) {
            $content = $this->readContentFS($filename);
        } else {
            $content = '';
        }

        // load metadata
        $yaml = $this->extractYaml($content);
        if ($enrich) {
            $yaml = $this->enrichYaml($yaml);
        }

        // load page content
        $markdown = $this->extractMarkup($content);

        // done
        return array($yaml, $markdown);
    }

    /**
     * Recursively search for a snippet, starting in the current wiki page's
     * directory and going up the tree if necessary.
     *
     * @param string $name Basename of the snippet, e.g. '_banner.md'.
     * @return string $path of the closest Snippet found.
     */
    private function findSnippetPath(
        string $name
    ): string {
        $split = explode('/', trim($this->wikiPath, '/'));
        while (count($split) > 0) {
            $lookup = implode('/', $split);
            if (is_file($this->contentDirFS . '/' . $lookup . "/$name")) {
                return '/' . $lookup . '/' . $name;
            }
            array_pop($split);
        }
        return '/' . $name;
    }

    /**
     * Log a file change.
     */
    private function addToChangelog(): void
    {
        $changelog = $this->contentDirFS . '/CHANGELOG.md';
        touch($changelog); // make sure file exists

        $log = '* [' . $this->getTitle() . '](' . $this->wikiPath . ')'
            . ' ' . $this->getAuthor()
            . ' ' . $this->metadata['date']
            . PHP_EOL;

        $this->writeContentFS($changelog, $log . $this->readContentFS($changelog), LOCK_EX);
    }
}

abstract class WikiPlugin // phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
{
    protected $wiki;
    protected $config;
    protected $core;
    public $user;

    public function __construct($wiki, $core, $user, $config)
    {
        $this->wiki = $wiki;
        $this->core = $core;
        $this->user = $user;
        $this->config = $config;
    }

    abstract public function setup();
}
