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

?>
<section class="section-has-bg navbar">
  <nav class="container">
    <div class="row">
      <div class="col-12">
        <?php echo $wiki->getSnippetHTML('topnav'); ?>
        <div>
          <input id="wiki-burger" style="display:none;" type="checkbox">
          <label for="wiki-burger">⚙️</label>
          <div class="wiki-menu">
            <?php echo getPageLinksHTML($user, $wiki); ?>
          </div>
        </div>
      </div>
    </div>
  </nav>
</section>
