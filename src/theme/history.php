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

outputHeader($config, ___('History') . ': ' . $wiki->getTitle(), 'page history');
outputNavbar($wiki, $user);
outputBanner($wiki);

?><section class="section-main container page-history">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <h2><?php __('History for %s', $wiki->getPath()); ?></h2>
      <?php if ($wiki->isDirty()) { ?>
        <p><?php __('The checksum of this page is invalid. Save the page in wiki.md again to correct this.') ?>
      <?php } ?>
      <?php if ($history === null) { ?>
        <div id="history-0" class="card"><p><?php __('No history available.'); ?></p></div>
      <?php } else {
          $version = count($history) + 1;
            ?>
        <div id="history-<?php echo $version; ?>" class="card">
            <h3><?php __('Version'); ?> v<?php echo $version; ?> (<?php __('current'); ?>)</h3>
            <p><?php __('Date'); ?>: <?php echo htmlspecialchars($wiki->getDate()); ?></p>
            <p><?php __('Author'); ?>: <?php echo htmlspecialchars($wiki->getAuthor()); ?></p>
        </div>
          <?php
            $cards = '';
            foreach (array_reverse($history) as $change) { // hint: history is reverse-sorted
                $version--;
                $date = $change['date'];
                $author = $change['author'];
                $diff = gzuncompress(base64_decode($change['diff'])); ?>

                  <div class='card diff'>
                    <h4><?php __('Changes'); ?></h4>
                    <pre><?php echo $diff; ?></pre>
                  </div>

                  <div id="history-<?php echo $version; ?>" class='card'>
                    <h3><?php __('Version'); ?> v<?php echo $version; ?></h3>
                    <p><?php __('Date'); ?>: <?php echo htmlspecialchars($date); ?></p>
                    <p><?php __('Author'); ?>: <?php echo htmlspecialchars($author); ?></p>
                    <?php if (!$wiki->isDirty()) { ?>
                    <form action='?action=restore&version=<?php echo $version; ?>' method='post'>
                      <input class="btn primary" type="submit" value="<?php __('restore %s', 'v' . $version); ?>">
                    </form>
                    <?php } ?>
                  </div>
            <?php } ?>
      <?php } ?>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $wiki->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php outputFooter($wiki); ?>
