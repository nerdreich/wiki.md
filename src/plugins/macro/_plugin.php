<?php // phpcs:ignore

/**
 * Copyright 2020-2026 Markus Leupold-Löwenthal
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

if (!class_exists('\at\nerdreich\wiki\MacroPlugin')) {

    /**
     * Macro expansion plugin for wiki.md.
     *
     * Will add the capability of using {{...}} macros in markdown and provide a
     * a few default macros.
     */
    class MacroPlugin extends WikiPlugin
    {
        private $macros = [];          // array of {{macro ...}} handlers

        public function setup(): void
        {
            // register core macros
            $this->registerMacro('include', function (?string $primary, ?array $secondary, string $path) {
                return $this->macroInclude($primary, $secondary, $path);
            });
            $this->registerMacro('paginate', function (?string $primary, ?array $secondary, string $path) {
                return $this->macroPaginate($primary, $secondary, $path);
            });

            // register plugin itself
            $this->core->registerFilter('raw', 'macros', function (string $markup, string $pathFS): string {
                $markup = preg_replace_callback('/{{[^}]*}}/', function ($matches) use ($pathFS) {
                    list($command, $primary, $secondary) = $this->splitMacro($matches[0]);
                    if (array_key_exists($command, $this->macros)) {
                        return $this->macros[$command]($primary, $secondary, $pathFS);
                    }
                    return $matches[0];
                }, $markup);
                return $markup;
            });
        }

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
                    list($key, $value) = explode('=', "$secondaryPair=");
                    $secondary[trim($key)] = trim($value ?? '');
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
        private function macroInclude(
            ?string $includePath,
            ?array $options,
            string $pathFS
        ): string {

            if ($includePath === null || $includePath === '') {
                return '{{error include-invalid}}';
            }

            // now we need to convert the potentially relative $includePath in an absolute $wikiPath
            if (strpos($includePath, '/') === 0) { // absolute include
                $wikiPath = $this->core->canonicalWikiPath($includePath);
            } else { // relative include
                $wikiPathCaller = $this->core->contentFileFSToWikiPath($pathFS);
                $wikiPath = $this->core->canonicalWikiPath(
                    $this->core->getWikiPathParentFolder($wikiPathCaller) . $includePath
                );
            }

            // deny caller walking up too far / outside the wiki dir
            if ($wikiPath === null) {
                return '{{error include-permission-denied}}';
            }

            // now we fetch the included file's content if possible
            $includeFileFS = $this->core->wikiPathToContentFileFS($wikiPath);
            if ($this->core->mayReadPath($wikiPath)) {
                if (is_file($includeFileFS)) {
                    list($metadata, $content) = $this->core->loadFile($includeFileFS);
                    return $this->core->markupToHTML($content, $includeFileFS);
                } else {
                    return '{{error include-not-found}}';
                }
            } else {
                return '{{error include-permission-denied}}';
            }
        }

        /**
         * Expand a {{paginate ...}} macro.
         *
         * @param string $primary The primary parameter. Pattern to match items to paginate.
         * @param array $options The secondary parameters. Not used.
         * @param string $pathFS Absolute path to file containing the macro (for relative processing).
         * @return string Expanded macro.
         */
        private function macroPaginate(
            ?string $primary,
            ?array $options,
            string $pathFS
        ): string {
            $snippet = '';
            $pages = [];
            $myIndex = -1;
            $basename = basename($pathFS);

            // load all matching files
            $pattern = '/^' . str_replace('*', '.*', $primary) . '$/';
            foreach (scandir(dirname($pathFS)) as $filename) {
                if (preg_match($pattern, $filename)) {
                    if (is_file(dirname($pathFS) . '/' . $filename)) {
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
        }
    }

    $GLOBALS['wiki.md-plugins']['macro'] = '\at\nerdreich\wiki\MacroPlugin';
}
