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

outputHeader($wiki, ___('Edit') . ': ' . $wiki->core->getTitle());
outputNavbar($wiki);
outputBanner($wiki);

?>
<section class="section-main container">
  <div class="row">
    <form class="col-12" action="?page=save" method="post">
      <?php if ($wip = $wiki->core->isWip()) { ?>
        <p class="warning"><?php __('Warning') ?>: <?php __('Someone started editing this file %s minutes ago.', ceil($wip / 60.)) ?></p>
      <?php } ?>
      <?php $wiki->echoIf('<h1>', $wiki->core->getTitle(), '</h1>'); ?>
      <label for="title" class="in-border"><?php __('Title - may remain empty'); ?></label>
      <input id="title" type="text" name="title" value="<?php echo $wiki->core->getTitle(); ?>">
      <label for="content" class="in-border"><?php __('Markdown'); ?></label>
      <textarea id="content" name="content" required autofocus><?php echo $wiki->core->getContentMarkup(); ?></textarea>
      <label for="author" class="in-border"><?php __('Author'); ?></label>
      <input id="author" type="text" name="author" value="<?php echo $wiki->user->getAlias(); ?>" required>
      <input type="submit" class="primary" name="save" value="<?php __('Save'); ?>"><input type="submit" name="edit"
      value="<?php __('Save & Edit'); ?>"><a class="btn" href="<?php echo $wiki->core->getLocation(); ?>"><?php __('Cancel'); ?></a>
    </form>
    <div class="col-12">
      <hr>
    </div>
    <div class="col-12">
      <p>
        <strong><?php __('Quickhelp'); ?>:</strong>
        <code>_<?php __('italic'); ?>_</code> |
        <code>**<?php __('bold'); ?>**</code> |
        <code>``<?php __('courier'); ?>``</code> |
        <code>~~<?php __('strike through'); ?>~~</code> |
        <code>[<?php __('link title'); ?>](<?php __('target'); ?>)</code> |
        <code>#</code>/<code>##</code>/<code>###</code>&nbsp;<?php __('Headlines'); ?> |
        <code>---</code>&nbsp;<?php __('Separator'); ?>
      </p>
    </div>
  </div>
</section>
<?php outputFooter($wiki); ?>
