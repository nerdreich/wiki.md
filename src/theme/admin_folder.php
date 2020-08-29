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

outputHeader($config, $wiki->getWikiPath(), ___('Edit') . ': ' . $wiki->getTitle(), ' admin');
outputNavbar($wiki, $user);

$infos = $user->adminFolder($wiki->getWikiPath());

$userPattern = '(' . implode(',|', $infos['users']) . ',)*(' . implode('|', $infos['users']) . ')?|\*';

?>
<section class="banner">
  <nav class="container">
    <div class="row">
      <div class="col-12">
        <h1 class="h2"><?php __('Folder editor'); ?></h1>
      </div>
    </div>
  </nav>
</section>
<section class="section-main container">
  <div class="row">
    <form class="col-12" action="?admin=permissions" method="post">
      <h2><?php __('Folder permissions'); ?></h2>
      <p class="minor"><?php __('valid for %s and below', $infos['folder']); ?></p>
      <label class="in-border"><?php __('Create'); ?></label>
      <input type="text" name="userCreate" value="<?php echo $infos['permissions']['userCreate']; ?>"
        placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
      <label class="in-border"><?php __('Read'); ?></label>
      <input type="text" name="userRead" value="<?php echo $infos['permissions']['userRead']; ?>"
        placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
      <label class="in-border"><?php __('Update'); ?></label>
      <input type="text" name="userUpdate" value="<?php echo $infos['permissions']['userUpdate']; ?>"
        placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
      <label class="in-border"><?php __('Delete'); ?></label>
      <input type="text" name="userDelete" value="<?php echo $infos['permissions']['userDelete']; ?>"
        placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
      <label class="in-border"><?php __('Admin'); ?></label>
      <input type="text" name="userAdmin" value="<?php echo $infos['permissions']['userAdmin']; ?>"
        placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
      <input type="submit" class="primary" value="<?php __('Save'); ?>">
    </form>
    <form class="col-12" action="?admin=secret" method="post">
      <h2><?php __('Users (global)'); ?></h2>
      <label class="in-border"><?php __('Username'); ?></label>
      <input type="text" name="username" pattern="[a-zA-Z]{2,32}">
      <label class="in-border"><?php __('Password'); ?></label>
      <input type="text" name="secret" minlength="6" maxlength="128">
      <input type="submit" class="primary" value="<?php __('Set password'); ?>">

      <ul>
          <?php
            foreach ($infos['users'] as $entry) {
                echo '<li>' . $entry;
                if ($entry !== $user->getSuperuser()) {
                    echo ' - <a href="?admin=delete&amp;user=' . $entry . '">' . ___('Delete') . '</a>';
                }
                echo '</li>';
            } ?>
      </ul>
    </form>
  </div>
</section>
<?php outputFooter($wiki, $config); ?>
