<?php

/**
 * Copyright 2020-2022 Markus Leupold-Löwenthal
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

require_once(dirname(__FILE__) . '/../../core/Translate.php');
at\nerdreich\wiki\Translate::loadLanguage(dirname(__FILE__) . '/I18N/' . $wiki->getLanguage() . '.yaml');

// --- register theme macros ---------------------------------------------------

$wiki->core->getPlugin('macro')->registerMacro('paginate', function (
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

/**
 * Convert a wiki path into a series of CSS classes.
 *
 * This is usefull to style pages depending on their folder or name. E.g.
 * `/animal/lion` -> `page page-animal page-animal-lion`
 *
 * @param string Wiki path to convert.
 * @return string Class string to be added to class="".
 */
function pathToClasses(
    string $wikiPath
): string {
    $css = '';
    $prefix = 'page';
    foreach (explode('/', $wikiPath) as $element) {
        if ($element === '') {
            $css = $prefix;
        } else {
            $css .= ' ' . $prefix . '-' . $element;
            $prefix = $prefix . '-' . $element;
        }
    }
    return trim($css);
}

/**
 * Beautify a udiff as html.
 *
 * @param string $diff The diff's content
 * @return string HTML markup for the diff.
 */
function diff2html(
    string $diff
): string {
    $html = '';
    $chunk = '';
    foreach (explode("\n", $diff) as $line) {
        switch ($line[0] ?? '') {
            case '@':
                $html .= $chunk . "\n<span class=\"info\">" . $line . "</span>\n";
                $chunk = '';
                break;
            case '-':
                $chunk = $chunk . '<span class="removed">' . substr($line, 1) . "</span>\n";
                break;
            case '+':
                $chunk = $chunk . '<span class="added">' . substr($line, 1) . "</span>\n";
                break;
        }
    }
    $html .= $chunk;
    return '<pre><code>' . substr($html, 1) . '</code></pre>';
}

/**
 * Convert a date or time to a string based on the current language/locale.
 *
 * Will auto-detect the format of the date.
 *
 * @param mixed $param A date of some kind.
 * @return string Formatted date.
 */
function localDateString(
    $param
): string {
    global $wiki;
    if (gettype($param) === 'object' && get_class($param) === 'DateTime') {
        return $param->format($wiki->getDateTimeFormat());
    }
    if (gettype($param) === 'integer') {
        return (new \DateTime('@' . $param))->format($wiki->getDateTimeFormat());
    }
    return $param;
}

// --- output ------------------------------------------------------------------

/**
 * Assemble the navigation menu.
 *
 * @param\at\nerdreich\wiki\WikiUI $wiki Current UI object.
 */
function getPageLinksHTML(at\nerdreich\wiki\WikiUI $wiki): string
{
    $html = '';
    foreach ($wiki->getMenuItems() as $action => $label) {
        $html .= '<a href="?' . $action . '">' . ___($label) . '</a><br>';
    }
    return $html;
}

/**
 * Generate the HTML header and open the <body>.
 *
 * @param\at\nerdreich\wiki\WikiUI $wiki Current UI object.
 */
function outputHeader(at\nerdreich\wiki\WikiUI $wiki, ?string $title = null, ?string $description = null): void
{
    ?><!doctype html>
<html class="no-js" lang="">
<head>
  <meta charset="utf-8">
    <?php
    echo $title === null ? '' : '<title>' . htmlspecialchars($title) . '</title>';
    echo $description === null ? '' : '<meta name="description" content="' . htmlspecialchars($description) . '">';
    ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="manifest" href="<?php echo htmlspecialchars($wiki->getThemePath()); ?>site.webmanifest">
  <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($wiki->getThemePath()); ?>icon.png">
  <link rel="icon" href="<?php echo htmlspecialchars($wiki->getThemePath()); ?>favicon.ico"  type="image/x-icon">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($wiki->getThemePath()); ?>style.css?v=$VERSION$">
</head>
<body class="<?php echo htmlspecialchars(pathToClasses($wiki->core->getWikiPath())); ?>">
    <?php
}

/**
 * Generate the (top) navbar.
 *
 * @param\at\nerdreich\wiki\WikiUI $wiki Current UI object.
 */
function outputNavbar(at\nerdreich\wiki\WikiUI $wiki): void
{
    ?>
<section class="navbar">
  <nav class="container">
    <div class="row">
      <div class="col-12">
        <?php echo $wiki->core->getSnippetHTML('topnav'); ?>
        <div class="wiki-menu-container">
          <input id="wiki-burger" type="checkbox">
          <label for="wiki-burger"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></label>
          <div class="wiki-menu">
            <?php echo getPageLinksHTML($wiki); ?>
          </div>
        </div>
      </div>
    </div>
  </nav>
</section>
    <?php
}

/**
 * Generate the banner area.
 *
 * @param\at\nerdreich\wiki\WikiUI $wiki Current UI object.
 */
function outputBanner(at\nerdreich\wiki\WikiUI $wiki): void
{
    ?>
<section class="banner">
  <nav class="container">
    <div class="row">
      <div class="col-12">
        <?php echo $wiki->core->getSnippetHTML('banner'); ?>
      </div>
    </div>
  </nav>
</section>
    <?php
}

/**
 * Generate the footer and close <body> & <html>.
 *
 * @param\at\nerdreich\wiki\WikiUI $wiki Current UI object.
 */
function outputFooter(at\nerdreich\wiki\WikiUI $wiki): void
{
    ?>
<footer class="container">
  <div class="row">
    <div class="col-12">
      <p>
        <a class="no-icon" href="<?php echo $wiki->getRepo(); ?>">wiki.md v<?php echo $wiki->core->getVersion(); ?></a>
        <?php if ($wiki->core->getDate() !== null) {
            echo '- ' . htmlspecialchars(___('Last saved %s', localDateString($wiki->core->getDate())));
        } ?>
        - <a href="/<?php __('Privacy'); ?>"><?php __('Privacy'); ?></a>
      </p>
    </div>
  </div>
</footer>
</body>
</html>
    <?php
}
