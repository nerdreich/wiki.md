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

namespace at\nerdreich\wiki {
    class Translate
    {
        private static $translations = [];

        /**
         * Load translation data from a .yaml file.
         *
         * Only has very limited 'yaml' support:
         * - Will only load top-level entries.
         * - Does not support escaping of colons within the texts.
         *
         * @param string $yamlfile .yaml/language file to load.
         * @return bool True if this language file could be loaded. False if we
         *              had to revert to default language.
         */
        public static function loadLanguage(
            string $yamlfile
        ): bool {
            self::$translations = [];
            if (is_file($yamlfile)) {
                $lines = file($yamlfile);
                foreach ($lines as $line) {
                    $line = preg_replace('/#.*/', '', $line); // remove comments
                    if (strlen(trim($line)) <= 0) { // skip empty lines
                        continue;
                    }
                    list($key, $value) = explode(':', ltrim($line, "- \t"));
                    self::$translations[trim($key)] = trim($value);
                }
                return count(self::$translations) > 0;
            }
            return false;
        }

        /**
         * Translate a string.
         *
         * @param $args[0] String to translate. Supports printf / format specifiers.
         * @param $args[1..5] printf parameters (max 4) to be applied after translating.
         * @return string Translated string, or original string if no translation was found.
         */
        public static function translate(
            array $args
        ): string {
            $translation = self::$translations[$args[0]] ?? $args[0];
            switch (sizeof($args)) {
                case 2:
                    $translation = sprintf($translation, $args[1]);
                    break;
                case 3:
                    $translation = sprintf($translation, $args[1], $args[2]);
                    break;
                case 4:
                    $translation = sprintf($translation, $args[1], $args[2], $args[3]);
                    break;
                case 5:
                    $translation = sprintf($translation, $args[1], $args[2], $args[3], $args[4]);
                    break;
            }

            return $translation;
        }
    }
}
namespace { // global helpers to reduce clutter in templates

    /**
     * Translate & HTML-escape text.
     *
     * Will echo the translated text. Mainly for use in HTML templates.
     *
     * @param $args[0] String to translate.
     * @param $args[1..5] printf parameters to be applied after translating.
     */
    function __(): void
    {
        echo htmlspecialchars(\at\nerdreich\wiki\Translate::translate(func_get_args()));
    }

    /**
     * Translate text.
     *
     * Will not echo or escape the result - that's up for the caller to do.
     *
     * @param $args[0] String to translate. Supports printf / format specifiers.
     * @param $args[1..5] printf parameters to be applied after translating.
     * @return string Translated string, or original string if no translation was found.
     */
    function ___(): string
    {
        return \at\nerdreich\wiki\Translate::translate(func_get_args());
    }

}
