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

outputHeader($config, ___('Edit') . ': ' . $wiki->getTitle(), 'page editor');
outputNavbar($wiki, $user);
outputBanner($wiki);

?>
<section class="section-main container">
  <div class="row">
    <form class="col-12" action="?action=save" method="post">
      <?php if ($wiki->isDirty()) { ?>
        <p><?php __('The checksum of this page is invalid. Save the page in wiki.md again to correct this.') ?></p>
      <?php } ?>
      <?php echo $wiki->getTitle() !== '' ? '<h1>' . htmlspecialchars($wiki->getTitle()) . '</h1>' : ''; ?>
      <input type="text" name="title" placeholder="<?php __('Title - may remain empty'); ?>" value="<?php echo $wiki->getTitle(); ?>">
      <textarea name="content" placeholder="<?php __('Content'); ?>" required><?php echo $wiki->getMarkup(); ?></textarea>
      <input type="text" name="author" placeholder="<?php __('Author'); ?>" value="<?php echo $user->getAlias(); ?>" required>
      <input type="submit" class="primary" value="<?php __('Save'); ?>"><input type="submit"
      value="<?php __('Save & Edit'); ?>"><a class="btn" href="<?php echo $wiki->getPath(); ?>"><?php __('Cancel'); ?></a>
    </form>
    <div class="col-12">
        <p><strong><?php __('Quickhelp'); ?>:</strong>
          <code>_<?php __('italic'); ?>_</code> |
          <code>**<?php __('bold'); ?>**</code> |
          <code>``<?php __('courier'); ?>``</code> |
          <code>~~<?php __('strike through'); ?>~~</code> |
          <code>[<?php __('link title'); ?>](<?php __('target'); ?>)</code> |
          <code>#</code>/<code>##</code>/<code>###</code>&nbsp;<?php __('Headlines'); ?> |
          <code>---</code>&nbsp;<?php __('Separator'); ?>
    </div>
  </div>
</section>
<?php outputFooter($wiki); ?>
