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

    private $filename = 'na'; // fs-path to content file, e.g. /var/www/www.mysite.com/mywiki/data/animals/lion.md
    private $wikiroot = '';   // fs-path to wiki code, e.g. /var/www/www.mysite.com/mywiki
    private $dataPath = '';   // fs-path to wiki data, e.g. /var/www/www.mysite.com/mywiki/data
    private $urlRoot = '';    // url-parent-folder of the wiki, e.g. /mywiki
    private $urlPath = '/';   // current URL path within the wiki, e.g. /animals/lion

    private $metadata = [];   // yaml front matter for a content
    private $content = '';    // the markdown body for a content

    private $macros = [];     // array of {{macro ...}} handlers

    /**
     * Constructor
     *
     * @param array $config Array with loaded config.ini as key => value entries.
     */
    public function __construct(
        array $config
    ) {
        $this->config = $config;

        // wiki path + files
        $this->wikiroot = dirname(dirname(__FILE__)); // Wiki.php is in the ../core folder
        $this->dataPath = $this->wikiroot . '/' . ($this->config['datafolder'] ?? 'data') . '/content';
        $this->urlRoot = substr($this->wikiroot, strlen($_SERVER['DOCUMENT_ROOT']));

        // register core macros
        $this->registerMacro('include', function (?string $primary, ?array $secondary, string $path) {
            return $this->resolveMacroInclude($primary, $secondary, $path);
        });
    }

    /**
     * Load a page.
     *
     * Will also fix invalid/hidden dir/README combinations.
     *
     * @param string $urlPath The URI path for the wiki page to load/process.
     */
    public function load(
        string $urlPath
    ): void {
        // hide /dir/README.md behind /dir/
        if (preg_match('/README$/', $urlPath)) {
            $this->redirect(dirname($urlPath) . '/');
        }

        // assemble absolute fs url
        $this->urlPath = $urlPath;
        $this->filename = $this->findContentFileForURLPath($this->urlPath);

        // if this is both a file and a folder, redirect to the folder instead
        if (is_dir(preg_replace('/\.md$/', '/', $this->filename))) {
            $this->redirect($urlPath . '/');
        }

        list($this->metadata, $this->content) = $this->loadFile($this->filename, true);
    }

    /**
     * Redirect to a wiki path, e.g. /animals/lion.
     *
     * If wiki.md is installed in a sub-folder, it will prefix the location
     * with it, e.g. /mywiki/animals/lion.
     *
     * @param string $urlPath The URI path for the wiki page.
     */
    private function redirect(
        string $path
    ): void {
        if ($this->urlRoot . $path === '') {
            header('Location: /');
        } else {
            $path = preg_replace('/\/+/', '/', $path); // remove double slashed
            header('Location: ' . $this->urlRoot . $path);
        }
        die();
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
     * Check if the current wiki page exists.
     *
     * @return boolean True, if the current path matches a page. False if not.
     */
    public function exists(): bool
    {
        return is_file($this->filename);
    }

    /**
     * Get the user-visible path for the current wiki page.
     *
     * In case wiki.md was installed in a sub-directory, this path does not
     * contain it.
     *
     * @return string URI Path, e.g. '/animals/lion'.
     */
    public function getPath(): string
    {
        return $this->urlPath;
    }

    /**
     * Get the parent directory of the wiki.
     *
     * @return string URL Path, e.g. '/mywiki'.
     */
    public function getPathRoot(): string
    {
        return $this->urlRoot;
    }

    /**
     * Get the full url path including the url root.
     *
     * @return string URL Path, e.g. '/mywiki/animals/lion'.
     */
    public function getLocation(): string
    {
        return $this->urlRoot . $this->urlPath;
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
    public function getDate(): string
    {
        if (array_key_exists('date', $this->metadata)) {
            return \DateTime::createFromFormat(\DateTimeInterface::ATOM, $this->metadata['date'])->format('d.m.Y H:i');
        }
        return 'n/a';
    }

    /**
     * Get the HTML content for the current wiki page.
     *
     * @return string HTML snippet for the content part of this page.
     */
    public function getContentHTML(): string
    {
        return $this->markdown2Html(
            $this->preprocessMarkdown($this->content, $this->filename)
        );
    }

    /**
     * Get the raw Markdown markup for the current wiki page.
     *
     * @return string The markup for this wiki page.
     */
    public function getMarkup(): string
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
        $filename = $this->dataPath . $this->findSnippetPath("_$snippetName.md");
        $html = $this->getHTML($filename);

        // convert headlines to look-alike divs
        $html = preg_replace('/<h([1-6])>/', '<div class="h$1">', $html);
        $html = preg_replace('/<\/h[1-6]>/', '</div>', $html);

        return $html;
    }

    /**
     * Load a content file as HTML.
     *
     * @param string $markdownPath Absolute path to .md file.
     * @return string Rendered HTML.
     */
    public function getHTML(
        string $markdownPath
    ): string {
        list($snippetMetadata, $snippetContent) = $this->loadFile($markdownPath);
        return $this->markdown2Html(
            $this->preprocessMarkdown($snippetContent, $markdownPath)
        );
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
        if ($this->isDirty()) {
            // direct changes in the FS prevent the history from working
            return false;
        }
        $historySize = count($this->metadata['history']);
        if ($version > 0 && $version <= $historySize) {
            // reverse-apply all diffs up to to the requested version
            for ($revertTo = $historySize; $revertTo >= $version; $revertTo--) {
                $diffToApply = $revertTo - 1;
                $diff = gzuncompress(base64_decode($this->metadata['history'][$diffToApply]['diff']));
                $this->content = \at\nerdreich\UDiff::patch($this->content, $diff, true);
            }
            return true;
        }

        return false;
    }

    /**
     * Undo one / the last version of this page.
     *
     * Will update class data to reflect this version. Will silently fail if
     * the given version number does not exist.
     *
     * @return string True if version could be applied.
     */
    private function revertToPreviousVersion(): bool
    {
        if (array_key_exists('history', $this->metadata)) {
            $count = count($this->metadata['history']);
            if ($count > 0) {
                return $this->revertToVersion(count($this->metadata['history']));
            }
        }
        $this->content = ''; // without history the previous version was empty
        return true;
    }

    // ----------------------------------------------------------------------
    // --- {{macro}} handling  ----------------------------------------------
    // ----------------------------------------------------------------------

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
     * @param array $secondary The secondary parameters. Not used.
     * @param string $path Absolute path to file containing the macro (for relative processing).
     * @return string Expanded macro.
     */
    private function resolveMacroInclude(
        ?string $primary,
        ?array $secondary,
        string $path
    ): string {
        $includePath = dirname($path) . '/' . $primary . '.md';
        if (is_file($includePath)) {
            return $this->getHTML($includePath);
        } else {
            return '{{error include-not-found}}';
        }
    }

    /**
     * Expand all {{...}} macros with their dynamic content.
     *
     * @param string $markdown A markdown body of a page or snippet.
     * @param string $path Absolute path to file containing the macros (for relative processing).
     * @return string New markdown with all macros expanded.
     */
    private function resolveMacros(
        string $body,
        string $path
    ): string {
        if (preg_match_all('/{{[^}]*}}/', $body, $matches)) {
            foreach ($matches[0] as $macro) {
                list($command, $primary, $secondary) = $this->splitMacro($macro);
                if (array_key_exists($command, $this->macros)) {
                    $body = str_replace(
                        $macro,
                        $this->macros[$command]($primary, $secondary, $path),
                        $body
                    );
                }
            }
        }
        return $body;
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

    /** Determine if the file has been changed on disk.
     *
     * wiki.md assumes that only this class makes changes to .md files. If
     * someone else does, the page content hash will no longer match and the
     * page history will no longer work. (To correct this, the page just has
     * to be re-saved and the hash re-calculated.)
     *
     * @return True, if content in filesystem does not match with our hash.
     */
    public function isDirty(): bool
    {
        if (array_key_exists('hash', $this->metadata)) {
            $hash = hash('sha1', $this->content);
            return $hash !== $this->metadata['hash'];
        }
        return false; // no hash in headers -> this page has not yet been saved by wiki.md
    }

    /**
     * Save a page. Create a new diff/history on the fly if it already existed.
     *
     * @param $content New markdown content for this page.
     * @param $title New title of this page.
     * @param $author Name to store as author for this change.
     * @return bool True, if the page could be saved.
     */
    public function savePage(
        string $content,
        string $title,
        string $author
    ): bool {
        $author = $this->cleanupSingeLineText($author);
        $title = $this->cleanupSingeLineText($title);

        // check if this is yet another quick save by the same author
        if ($this->metadata['author'] === $author) {
            if (array_key_exists('date', $this->metadata)) {
                $lastSaveDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $this->metadata['date']);
                $seconds = (new \DateTime())->getTimestamp() - $lastSaveDate->getTimestamp();
                if ($seconds < $this->config['autosquash_interval'] ?? -1) {
                    // this is a quick (re)save. undo last history to merge the saves into one.
                    if ($this->revertToPreviousVersion()) {
                        if ($this->metadata['history'] === []) {
                            unset($this->metadata['history']);
                        } else {
                            array_pop($this->metadata['history']);
                        }
                    } else {
                        return false;
                    }
                }
            }
        }

        // calculate diff & hash
        $diff = \at\nerdreich\UDiff::diff($this->content, $content);
        $hash = hash('sha1', $content);

        if (array_key_exists('history', $this->metadata)) {
            // page has a history -> add to that

            // create a new history entry
            $historyEntry = [];
            $historyEntry['author'] = $this->metadata['author'] ?? 'unknown';
            $historyEntry['date'] =
                $this->metadata['date'] ?? date(\DateTimeInterface::ATOM, filemtime($this->filename));
            $diff = preg_replace('/^.+\n/', '', $diff); // remove first line (---)
            $diff = preg_replace('/^.+\n/', '', $diff); // remove second line (+++)
            $historyEntry['diff'] = chunk_split(base64_encode(gzcompress($diff)), 64, "\n");

            $this->metadata['history'][] = $historyEntry;
        } else {
            // no history exists -> this is the first save, just start a new/empty one
            $this->metadata['history'] = [];
        }

        // update yaml front matter / metadata
        $this->metadata['date'] = date(\DateTimeInterface::ATOM);
        if ($title !== '') {
            $this->metadata['title'] = $title;
        }
        if ($author !== '') {
            $this->metadata['author'] = $author;
        } else {
            $this->metadata['author'] = 'unknown';
        }
        $this->metadata['hash'] = $hash;

        // create parent dir if necessary
        if (!\file_exists(dirname($this->filename))) {
            mkdir(dirname($this->filename), 0777, true);
        }

        // write out & redirect to new content
        $frontmatter = \Spyc::YAMLDump($this->metadata);
        $this->fileWriteContent($this->filename, $frontmatter . "---\n" . trim($content) . "\n");
        $this->addToChangelog();

        return true;
    }

    /**
     * Create a new, empty page.
     *
     * Will only reset internal data to a blank page, to be picked up by the
     * editor.
     */
    public function createPage(): void
    {
        // reset internal data to empty page
        $this->metadata = [];
        $this->metadata['date'] = date(\DateTimeInterface::ATOM);
        $this->content = '';
        $this->filename = $this->dataPath . '/' . $this->urlPath . '.md';

        // prefill title
        $this->metadata['title'] = str_replace('_', ' ', basename($this->urlPath)); // underscore to spaces
        $this->metadata['title'] = preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->metadata['title']); // unCamelCase
    }

    /**
     * Delete the current wiki page.
     *
     * Will rename the markdownfile to '.deleted', making it invisible to the
     * wiki.
     */
    public function deletePage(): void
    {
        if ($this->exists()) {
            rename(
                $this->filename,
                $this->filename . '.deleted'
            );
        }
        $this->redirect($this->urlPath);
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
     * Render Raw Markdown content to HTML.
     *
     * @param string $markdown Markdown content.
     * @return string HTML.
     */
    private function markdown2Html(
        string $markdown
    ): string {
        $parser = new \ParsedownExtra();
        return $parser->text($markdown);
    }

    /**
     * Extract the Markdown part from a file's content.
     *
     * This just skips an (optional) YAML front matter part.
     *
     * @param string $content A file's raw content.
     * @return string Markdown part of this content.
     */
    private function extractMarkdown(
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
     * Convert relative to absolute links in Markdown content.
     *
     * Particularly usefull to fix snippets, which contain links relative to
     * the snippet location, not to the page location that includes them.
     *
     * @param string $markdown The Markdown content to fix.
     * @param string $fsPath The absolute folder this content is actually in.
     * @return string Fixed Markdown.
     */
    private function fixLinks(
        string $markdown,
        string $fsPath
    ): string {
        $folder = dirname($this->findURLPathForContentFile($fsPath));
        $folder = $folder === '/' ? '' : $folder;
        // add absolute path to all relative links
        //$markdown = preg_replace('/\]\(([^\/?])/', '](' . $folder . '/$1', $markdown);
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
                . $this->urlRoot . $folder . '/' . $matchLink[$index] . ')', $markdown);
        }
        return $markdown;
    }

    /**
     * Improve markdown for rendering.
     *
     * Will resolve relative links, fix headline depth, resolve macros ...
     *
     * @param string $markdown The Markdown content to fix.
     * @param string $fsPath The absolute fs-path to the content.
     * @return string Preprocessed Markdown.
     */
    private function preprocessMarkdown(
        string $markdown,
        string $fsPath
    ): string {
        // Make all Headlines one level deeper (# -> ##).
        $markdown = preg_replace('/^#/m', '##', $markdown);
        $markdown = $this->fixLinks($markdown, $fsPath);
        $markdown = $this->resolveMacros($markdown, $fsPath);
        return $markdown;
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
        $markdown = $this->extractMarkdown($content);

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
        $split = explode('/', trim($this->urlPath, '/'));
        while (count($split) > 0) {
            $lookup = implode('/', $split);
            if (is_file($this->dataPath . '/' . $lookup . "/$name")) {
                return '/' . $lookup . '/' . $name;
            }
            array_pop($split);
        }
        return '/' . $name;
    }

    /**
     * Map URL path to markdown file.
     *
     * - /path/to/folder/ -> /path/to/folder/README.md
     * - /path/to/item > /path/to/item.md
     *
     * @param string $urlPath Path to lookup.
     * @return mixed Path (string) to file or FALSE if not found.
     */
    private function findContentFileForURLPath(
        string $urlPath
    ): string {
        if (preg_match('/\/$/', $urlPath)) { // folder
            $postfix = 'README.md';
        } else { // page
            $postfix = '.md';
        }

        return $this->dataPath . $urlPath . $postfix;
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
    public function findURLPathForContentFile(
        string $filename
    ): string {
        $path = substr($filename, strlen($this->dataPath));
        $path = preg_replace('/.md$/', '', $path);
        return $path;
    }

    /**
     * Remove bad stuff from single-line inputs.
     *
     * @param string $text Value of an <input>.
     * @return string Trimmed value with extra whitspace removed.
     */
    private function cleanupSingeLineText(
        string $text
    ): string {
        return preg_replace('/\s+/', ' ', trim($text));
    }

    /**
     * Log a file change.
     */
    private function addToChangelog(): void
    {
        $changelog = $this->dataPath . '/CHANGELOG.md';
        touch($changelog); // make sure file exists

        $log = '* [' . $this->getTitle() . '](' . $this->getPath() . ')'
            . ' ' . $this->getAuthor()
            . ' ' . $this->metadata['date']
            . PHP_EOL;

        $this->fileWriteContent($changelog, $log . $this->fileReadContent($changelog), LOCK_EX);
    }
}