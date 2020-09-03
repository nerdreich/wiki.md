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

outputHeader($ui, $ui->wiki->getTitle(), $ui->wiki->getDescription());
outputNavbar($ui);
outputBanner($ui);

?>
<section class="section-main container">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <?php echo $ui->wiki->getTitle() !== '' ? '<h1>' . htmlspecialchars($ui->wiki->getTitle()) . '</h1>' : ''; ?>
      <?php echo $ui->wiki->getContentHTML(); ?>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $ui->wiki->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php outputFooter($ui); ?>
