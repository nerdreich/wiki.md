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

require_once('UDiff.php');              // simple file-diff implementation
require_once('lib/spyc.php');           // yaml parser
require_once('lib/Parsedown.php');      // markdown parser
require_once('lib/ParsedownExtra.php'); // better markdown parser

/**
 * wiki.md - A markdown wiki.
 */
class Wiki
{
    private $version = '$VERSION$';
    private $repo = '$URL$';
    private $config = [];
    private $user;
    private $pregMedia = '/\.(gif|jpg|png)$/i'; // files matching this are considered media
    private $mediaTypes = 'gif|jpg|png';        // shown to humans

    private $contentDirFS = '';    // e.g. /var/www/www.mysite.com/mywiki/data/content
    private $contentFileFS = 'na'; // e.g. /var/www/www.mysite.com/mywiki/data/content/animal/lion.md
    private $wikiRoot = '';        // url-parent-folder of the wiki, e.g. /mywiki
    private $wikiPath = '/';       // current document path within the wiki, e.g. /animal/lion

    private $metadata = [];   // yaml front matter for a content
    private $content = '';    // the markdown body for a content

    private $macros = [];     // array of {{macro ...}} handlers
    private $filters = [];

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
        $wikiDirFS = dirname(dirname(__FILE__)); // Wiki.php is in the ../core folder
        $this->contentDirFS = $wikiDirFS . '/' . ($this->config['datafolder'] ?? 'data') . '/content';
        $this->wikiRoot = substr($wikiDirFS, strlen($_SERVER['DOCUMENT_ROOT']));

        // register core filters
        $this->registerFilterFixLinks();
        $this->registerFilterIndentHeadlines();
        $this->registerFilterMacros();
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

    // ----------------------------------------------------------------------
    // --- various paths & converters ---------------------------------------
    // ----------------------------------------------------------------------

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
        if (preg_match('/\/$/', $wikiPath)) { // README / it's own folder
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
    private function canonicalWikiPath(string $wikiPath): ?string
    {
        $absPath = preg_replace('/\/$/', '/.', $wikiPath); // treat folder as dot-file
        if (strpos($absPath, '/') === 0) {
            // absolute path
            $absPath = $this->realpath($absPath);
        } else {
            // (probably) relative path
            $absPath = $this->realpath(dirname($this->wikiPath) . '/' . $absPath);
        }

        if ($absPath === null) { // relative path went out of wiki dir
            return null;
        }

        // keep a trailing slash but avoid doubles for the root
        if (preg_match('/\/$/', $wikiPath)) {
            $absPath = $absPath === '/' ? '/' : $absPath . '/';
        }

        return strlen($absPath) > 0 ? $absPath : '/';
    }

    /**
     * Determine the media directory for a wiki path.
     *
     * @param string $wikiPath Wiki Path.
     * @return string Absolute directory of media folder on disk.
     */
    private function getMediaDirFS(
        string $wikiPath
    ): string {
        if (preg_match('/\/$/', $wikiPath)) {
            return $this->contentDirFS . $wikiPath . '_media';
        } else {
            return $this->contentDirFS . dirname($wikiPath) . '/_media';
        }
    }

    /**
     * Determine the media file for a wiki path.
     *
     * @param string $wikiPath Wiki Path to a media file (e.g. /animal/lion.jpg).
     * @return string Absolute directory of media folder on disk.
     */
    private function getMediaFileFS(
        string $wikiPath
    ): string {
        return $this->getMediaDirFS($wikiPath) . '/' . basename($wikiPath);
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
    private function wikiPathToContentFileFS(
        string $wikiPath
    ): string {
        if (preg_match($this->pregMedia, $wikiPath)) { // image etc.
            return $this->getMediaFileFS($wikiPath);
        } else { // Markdown file
            if (preg_match('/\/$/', $wikiPath)) { // folder
                $postfix = 'README.md';
            } else { // page
                $postfix = '.md';
            }
            return $this->contentDirFS . $wikiPath . $postfix;
        }
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
        if (preg_match('/\/$/', $filename)) {
            // if this is a file, we switch to it's folder
            $filename = dirname($filename);
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
        $path = '/' . join('/', $path);

        return $path;
    }

    // ----------------------------------------------------------------------
    // --- content access for theme files -----------------------------------
    // ----------------------------------------------------------------------

    /**
     * Get the wiki.md version.
     *
     * @return string SemVer version, e.g. '1.0.2'.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the wiki.md source code repository URL.
     *
     * @return string Link to repo/homepage.
     */
    public function getRepo(): string
    {
        return $this->repo;
    }

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
     * Check if the current wiki page is a media object (image, ...).
     *
     * @return boolean True, if so. False if not.
     */
    public function isMedia(): bool
    {
        if (preg_match($this->pregMedia, $this->contentFileFS)) {
            if ($this->exists()) {
                return true;
            };
        }
        return false;
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
     * Get the media types allowed for upload.
     *
     * @return string Both human-readable and HTML5 pattern string for UI, e.g. 'gif|jpg|png'.
     */
    public function getMediaTypes(): string
    {
        return $this->mediaTypes;
    }

    /**
     * Get the media size limit.
     *
     * Might be limited by wiki.md's config or by php.ini.
     *
     * @return int Limit in bytes.
     */
    public function getMediaSizeLimit(): string
    {
        return min(
            (int)($this->config['media_size_limit_kb'] ?? 4096),
            ((int)(ini_get('upload_max_filesize'))) * 1024,
            ((int)(ini_get('post_max_size'))) * 1024,
            ((int)(ini_get('memory_limit'))) * 1024
        );
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
     * @param string $pathFS The absolute path to the content (for macros).
     * @return string HTML version.
     */
    private function markupToHTML(
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
        if ($this->user->mayRead($this->wikiPath) && $this->user->mayUpdate($this->wikiPath)) {
            $this->loadFS();

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
            $this->content = \at\nerdreich\UDiff::patch($this->content, $diff, true);
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

    // ----------------------------------------------------------------------
    // --- filter handling  -------------------------------------------------
    // ----------------------------------------------------------------------

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
        foreach ($this->filters[$hook] ?? [] as $plugin) {
            $markdown = $plugin($markdown, $fsPath);
        }
        return $markdown;
    }

    /**
     * Filter: Process {{...}} macros.
     */
    private function registerFilterMacros(): void
    {
        // register core macros
        $this->registerMacro('include', function (?string $primary, ?array $secondary, string $path) {
            return $this->resolveMacroInclude($primary, $secondary, $path);
        });

        // register plugin itself
        $this->registerFilter('raw', 'macros', function (string $markup, string $pathFS): string {
            if (preg_match_all('/{{[^}]*}}/', $markup, $matches)) {
                foreach ($matches[0] as $macro) {
                    list($command, $primary, $secondary) = $this->splitMacro($macro);
                    if (array_key_exists($command, $this->macros)) {
                        $markup = str_replace(
                            $macro,
                            $this->macros[$command]($primary, $secondary, $pathFS),
                            $markup
                        );
                    }
                }
            }
            return $markup;
        });
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

                preg_match_all('/\[([^]]*)\]\(([^)]*)\)/', $markdown, $matches); // fetch all markup links
                list($matchFull, $matchText, $matchLink) = $matches;
                for ($index = 0; $index < count($matchLink); $index++) {
                    if (preg_match('/^https?:/', $matchLink[$index])) { // skip http[s]: links
                        continue;
                    }

                    // at this point we can assume that we have an internal, absolute wikipath
                    if (!$this->exists($matchLink[$index])) {
                        // append Markdown-extra css markup to link
                        $markdown = str_replace($matchFull[$index], $matchFull[$index] . '{.broken}', $markdown);
                    }
                }
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

    // ----------------------------------------------------------------------
    // --- {{macro}} handling  ----------------------------------------------
    // ----------------------------------------------------------------------

    /**
     * Add a {{..}} macro to be processed by the macro filter.
     *
     * @param string $name The name of the macro (first parameter in {{...}}).
     * @param callable $handler A function that will process this macro.
     */
    public function registerMacro(
        string $name,
        callable $handler
    ): void {
        $this->macros[$name] = $handler;
    }

    /**
     * Split a {{macro}} into its components.
     *
     * @param string $macro The macro including curly braces.
     * @return array The components: name, primary parameter, secondary parameters.
     */
    public static function splitMacro(
        string $macro
    ): array {
        $macro = str_replace("\n", ' ', trim($macro));

        // check for macros without parameter
        if (preg_match_all('/{{\s*([^\s]+)\s*}}/', $macro, $matches)) {
            $command = trim($matches[1][0]);
            return [$command, null, null];
        }

        // now check for macro with only primary parameter
        if (preg_match_all('/{{\s*([^\s|]+)\s+([^\s|]+)\s*}}/', $macro, $matches)) {
            $command = trim($matches[1][0]);
            $primary = trim($matches[2][0]);
            return [$command, $primary, null];
        }

        // check for macro with extended secondary parameter
        if (preg_match_all('/{{\s*([^\s]+)\s+([^|]+)\s*\|(.*)}}/', $macro, $matches)) {
            $command = trim($matches[1][0]);
            $primary = trim($matches[2][0]);
            $secondary = [];
            $secondaryPairs = explode('|', trim($matches[3][0]));
            foreach ($secondaryPairs as $secondaryPair) {
                list($key, $value) = explode('=', $secondaryPair);
                $secondary[trim($key)] = trim($value);
            }
            return [$command, $primary, $secondary];
        }

        return [null, null, null];
    }

    /**
     * Expand a {{include ...}} macro.
     *
     * @param string $primary The primary parameter. Path to file to include. Can be relative.
     * @param array $options The secondary parameters. Not used.
     * @param string $pathFS Absolute path to file containing the macro (for relative processing).
     * @return string Expanded macro.
     */
    private function resolveMacroInclude(
        ?string $includePath,
        ?array $options,
        string $pathFS
    ): string {

        if ($includePath === null || $includePath === '') {
            return '{{error include-invalid}}';
        }

        // now we need to convert the potentially relative $includePath in an absolute $wikiPath
        if (strpos($includePath, '/') === 0) { // absolute include
            $wikiPath = $this->canonicalWikiPath($includePath);
        } else { // relative include
            $wikiPathCaller = $this->contentFileFSToWikiPath($pathFS);
            $wikiPath = $this->canonicalWikiPath(
                $this->getWikiPathParentFolder($wikiPathCaller) . $includePath
            );
        }

        // deny caller walking up too far / outside the wiki dir
        if ($wikiPath === null) {
            return '{{error include-permission-denied}}';
        }

        // now we fetch the included file's content if possible
        $includeFileFS = $this->wikiPathToContentFileFS($wikiPath);
        if ($this->user->mayRead($wikiPath)) {
            if (is_file($includeFileFS)) {
                list($metadata, $content) = $this->loadFile($includeFileFS);
                return $this->markupToHTML($content, $includeFileFS);
            } else {
                return '{{error include-not-found}}';
            }
        } else {
            return '{{error include-permission-denied}}';
        }
    }

    // ----------------------------------------------------------------------
    // --- file handling  ---------------------------------------------------
    // ----------------------------------------------------------------------

    /**
     * Read a file's content.
     *
     * Will take care of file locking.
     *
     * @param $filename Path to file to read.
     * @return Content, nor null if file could not be read.
     */
    private function fileReadContent(
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
    private function fileWriteContent(
        string $filename,
        string $content
    ): bool {
        return file_put_contents($filename, $content, LOCK_EX) !== false;
    }

    // ----------------------------------------------------------------------
    // --- page management --------------------------------------------------
    // ----------------------------------------------------------------------

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
        if ($this->user->mayRead($this->wikiPath) && $this->user->mayUpdate($this->wikiPath)) {
            if ($this->exists()) {
                $this->loadFS();
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
        $diff = \at\nerdreich\UDiff::diff($this->content, $newContent);
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
            $this->loadFS();
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
        if ($this->user->mayUpdate($this->wikiPath)) {
            $this->loadFS();

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
        $this->fileWriteContent(
            $this->contentFileFS,
            \Spyc::YAMLDump($this->metadata) . "---\n" . trim($this->content) . "\n"
        );
        touch($this->contentFileFS, $mtime);
    }

    /**
     * Load the content for this page from the filesystem.
     */
    private function loadFS(): void
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
        if ($this->user->mayCreate($this->wikiPath)) {
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
        if ($this->user->mayDelete($this->wikiPath)) {
            $this->loadFS();
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
        if ($this->user->mayRead($this->wikiPath)) {
            $this->loadFS();
            return true;
        }

        return false;
    }

    /**
     * Provide all data for the media/upload page.
     *
     * @param string $wikiPath WikiPath of folder to administer.
     * @param array Array containing 'media'.
     */
    public function media(
        string $wikiPath
    ): ?array {
        $mediaFolder = $this->getWikiPathParentFolder($wikiPath);
        if ($this->user->mayMedia($mediaFolder)) {
            $files = [];
            $mediaDirFS = $this->getMediaDirFS($mediaFolder);
            if (is_dir($mediaDirFS)) {
                foreach (new \DirectoryIterator($mediaDirFS) as $fileinfo) {
                    if (!$fileinfo->isDot() && preg_match($this->pregMedia, $fileinfo->getFilename())) {
                        $file = [];
                        $file['name'] = $fileinfo->getFilename();
                        $file['path'] = $fileinfo->getFilename();
                        $file['size'] = $fileinfo->getSize();
                        $file['mtime'] = $fileinfo->getMtime();
                        $files[] = $file;
                    }
                }
            }
            return $files;
        }
        return null;
    }

    /**
     * Delete a media file.
     *
     * @param string $wikiPath WikiPath of file to delete.
     * @return bool True if file could be deleted.
     */
    public function mediaDelete(
        string $wikiPath
    ): bool {
        if ($this->user->mayMedia($wikiPath)) {
            $file = $this->wikiPathToContentFileFS($wikiPath);
            if (is_file($file)) {
                unlink($file);
                return true;
            }
        }
        return false;
    }

    public function mediaUpload(
        $tempName,
        $filename,
        $wikiPath
    ): bool {
        if (preg_match($this->pregMedia, $filename)) { // image etc.
            if ($this->user->mayMedia($wikiPath)) {
                $target = $this->getMediaDirFS($wikiPath) . '/' . $filename;
                if (move_uploaded_file($tempName, $target)) {
                    return true;
                }
            }
        }
        return false;
    }

    // ----------------------------------------------------------------------
    // --- Content: Markdown & Meta -----------------------------------------
    // ----------------------------------------------------------------------

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
            $content = $this->fileReadContent($filename);
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

        $log = '* [' . $this->getTitle() . '](' . $this->getWikiPath() . ')'
            . ' ' . $this->getAuthor()
            . ' ' . $this->metadata['date']
            . PHP_EOL;

        $this->fileWriteContent($changelog, $log . $this->fileReadContent($changelog), LOCK_EX);
    }
}
