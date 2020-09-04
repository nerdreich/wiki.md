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

outputHeader($ui, ___('Media'));
outputNavbar($ui);

$plugin = $ui->wiki->getPlugin('media');
$files = $plugin->list($ui->wiki->getWikiPath());

?>
<section class="banner">
  <nav class="container">
    <div class="row">
      <div class="col-12">
        <h1 class="h2"><?php __('Media'); ?></h1>
      </div>
    </div>
  </nav>
</section>
<section class="section-main container">
  <div class="row">
    <div class="col-12">
      <?php if ($files) {
            echo '<table><thead><tr><th>File</th><th>Size</th><th>Date</th><th>Actions</th></tr></thead>' . PHP_EOL . '<tbody>';
            foreach ($files as $file) {
                echo '<tr><td><a href="' . urlencode($file['name']) . '" target="_blank">' .
                    $file['name'] . '</a></td><td>' . $plugin->mediaSize($file['size']) .
                    '</td><td>' . localDateString($file['mtime']) . '</td><td><a href="' .
                    urlencode($file['name']) . '?media=delete">' . ___('Delete') . '</a></td></tr>';
            }
            echo '</tbody></table>';
      } else {
          __('No media found.');
      } ?>
    </div>
    <form class="col-12 form-upload" action="?media=upload" method="post" enctype="multipart/form-data">
      <h2><?php __('Upload'); ?></h2>
      <p>
          <?php __('Allowed media types %s.', $plugin->getMediaTypes()); ?>
          <?php __('File size limit %s.', $plugin->mediaSize($plugin->getMediaSizeLimit() * 1024)); ?>
      </p>
      <input type="file" name="wikimedia" id="wikimedia"
        onchange="document.getElementById('filename').value = this.value">
      <input type="text" name="filename" id="filename"
        placeholder="<?php __('Select file'); ?>" pattern="|.*\.(<?php echo $plugin->getMediaTypes(); ?>)">
      <input type="submit" value="Upload" name="submit">
    </form>
  </div>
</section>
<?php outputFooter($ui); ?>
