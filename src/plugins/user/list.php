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

$plugin = $wiki->core->getPlugin('user');
$infos = $plugin->list();

$userPattern = '(' . implode(',|', $infos['users']) . ',)*(' . implode('|', $infos['users']) . ')?|\*';

?>
<form action="?user=set" method="post">
  <h2><?php __('Folder permissions'); ?></h2>
  <p class="minor"><?php __('valid for %s and below', $infos['folder']); ?></p>
  <label class="in-border"><?php __('Create'); ?></label>
  <input type="text" name="pageCreate" value="<?php echo $infos['permissions']['pageCreate'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <label class="in-border"><?php __('Read'); ?></label>
  <input type="text" name="pageRead" value="<?php echo $infos['permissions']['pageRead'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <label class="in-border"><?php __('Update'); ?></label>
  <input type="text" name="pageUpdate" value="<?php echo $infos['permissions']['pageUpdate'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <label class="in-border"><?php __('Delete'); ?></label>
  <input type="text" name="pageDelete" value="<?php echo $infos['permissions']['pageDelete'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <label class="in-border"><?php __('Upload'); ?></label>
  <input type="text" name="mediaAdmin" value="<?php echo $infos['permissions']['mediaAdmin'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <label class="in-border"><?php __('Admin'); ?></label>
  <input type="text" name="userAdmin" value="<?php echo $infos['permissions']['userAdmin'] ?? ''; ?>"
    placeholder="<?php __('like parent'); ?>" pattern="<?php echo $userPattern; ?>">
  <input type="submit" class="primary" value="<?php __('Save'); ?>">
</form>
<hr>
<form action="?user=secret" method="post">
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
            if ($entry !== $wiki->user->getSuperuser()) {
                echo ' - <a href="?user=delete&amp;name=' . $entry . '">' . ___('Delete') . '</a>';
            }
            echo '</li>';
        } ?>
  </ul>
</form>
