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

// load history
$history = $wiki->getHistory();

?><!doctype html>
<html class="no-js" lang="">
<head>
  <meta charset="utf-8">
  <title><?php __('History'); ?>: <?php echo htmlspecialchars($wiki->getTitle()); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/site.webmanifest">
  <link rel="apple-touch-icon" href="/icon.png">

  <link rel="stylesheet" href="/<?php echo $config['themePath']; ?>style.css?v=$VERSION$">
</head>
<body>
<?php include '_navbar.php' ?>
<?php include '_banner.php' ?>
<section class="section-main container">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <h2><?php __('History for %s', $wiki->getPath()); ?></h2>
      <?php if ($history === null) { ?>
        <div class="card"><p><?php __('No history available.'); ?></p></div>
      <?php } else { ?>
        <div class="card">
            <h3><?php __('Version'); ?> v<?php echo count($history) + 1; ?> (<?php __('current'); ?>)</h3>
            <p><?php __('Date'); ?>: <?php echo htmlspecialchars($wiki->getDate()); ?></p>
            <p><?php __('Author'); ?>: <?php echo htmlspecialchars($wiki->getAuthor()); ?></p>
        </div>
          <?php
            $cards = '';
            $version = 0;
            foreach ($history as $change) { // hint: history is reverse-sorted
                $version++;
                $date = $change['date'];
                $author = $change['author'];
                $diff = gzuncompress(base64_decode($change['diff'])); ?>

                  <div class='card'>
                    <h3><?php __('Version'); ?> v<?php echo $version; ?></h3>
                    <p><?php __('Date'); ?>: <?php echo htmlspecialchars($date); ?></p>
                    <p><?php __('Author'); ?>: <?php echo htmlspecialchars($author); ?></p>
                    <h4><?php __('Difference vs.'); ?> v<?php echo ($version + 1); ?></h4>
                    <pre><?php echo $diff; ?></pre>
                    <form action='?action=restore&version=$version' method='post'>
                      <input type="submit" value="<?php __('restore %s', 'v' . $version); ?>">
                    </form>
                  </div>

            <?php } ?>
      <?php } ?>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $wiki->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php include '_footer.php' ?>
</body>
</html>
