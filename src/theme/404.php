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

outputHeader($config, $wiki->getWikiPath(), ___('Not found'), '');
outputNavbar($wiki, $user);
outputBanner($wiki);

?>
<section class="section-main container">
  <div class="row">
    <div class="col-12">
      <p><?php __('Page %s does not exist.', $wiki->getLocation()); ?></p>
      <?php if ($user->mayCreate($wiki->getLocation())) { ?>
        <form action="?action=createPage" method="post">
          <input type="submit" class="primary" value="<?php __('Create page'); ?>">
        </form>
      <?php } ?>
    </div>
  </div>
</section>
<?php outputFooter($wiki, $config); ?>
