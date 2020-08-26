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

outputHeader($config, $wiki->getWikiPath(), ___('History') . ': ' . $wiki->getTitle(), 'page history');
outputNavbar($wiki, $user);
outputBanner($wiki);

function historyDate($date, $config): string
{
    if ($date === null) {
        return ___('unknown');
    }
    if (is_string($date)) { // transparently convert string date (from history)
        $date = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $date);
    }
    return $date->format($config['datetime']);
}

?><section class="section-main container page-history">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <h2><?php __('History for %s', $wiki->getWikiPath()); ?></h2>
      <?php if ($wiki->isDirty()) { ?>
        <p><?php __('The checksum of this page is invalid. Save the page in wiki.md again to correct this.') ?>
      <?php } ?>

      <dl class="timeline">
        <?php if ($history === null) { ?>
          <dt id="history-0">
            <p><?php __('No history available.'); ?></p>
          </dt>
        <?php } else { ?>
            <?php $version = count($history) + 1; ?>
            <dt id="history-<?php echo $version; ?>">
              <h2 class="h4"><?php __('Version'); ?> <?php echo $version--; ?> (<?php __('current'); ?>)</h2>
              <p>
                <span class="minor"><?php __('by %s at %s', $wiki->getAuthor(), historyDate($wiki->getDate(), $config)); ?></span>
              </p>
            </dt>
        <?php } ?>
        <?php foreach (array_reverse($wiki->getHistory() ?? []) as $change) { ?>
          <dd><?php echo diff2html(gzuncompress(base64_decode($change['diff']))); ?></dd>
          <dt id="history-<?php echo $version; ?>">
            <h2 class="h4"><?php __('Version'); ?> <?php echo $version; ?></h2>
            <p>
              <span class="minor"><?php __('by %s at %s', $change['author'], historyDate($change['date'], $config)); ?></span>
              <?php if (!$wiki->isDirty()) { ?>
                - <a href="?action=restore&version=<?php echo $version--; ?>"><?php __('restore'); ?></a>
              <?php } ?>
            </p>
          </dt>
        <?php } ?>
      </dl>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $wiki->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php outputFooter($wiki, $config); ?>
