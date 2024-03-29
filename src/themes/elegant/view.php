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

outputHeader($wiki, $wiki->core->getTitle(), $wiki->core->getDescription());
outputNavbar($wiki);
outputBanner($wiki);

?>
<section class="section-main container">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <?php $wiki->echoIf('<h1>', $wiki->core->getTitle(), '</h1>'); ?>
      <?php echo $wiki->core->getContentHTML(); ?>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $wiki->core->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php outputFooter($wiki); ?>
