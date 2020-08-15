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

?><!doctype html>
<html class="no-js" lang="">
<head>
  <meta charset="utf-8">
  <title><?php __('Edit'); ?>: <?php echo htmlspecialchars($wiki->getTitle()); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/site.webmanifest">
  <link rel="apple-touch-icon" href="/icon.png">

  <link rel="stylesheet" href="/<?php echo $config['themePath']; ?>style.css?v=$VERSION$">
</head>
<body>
<?php include '_navbar.php' ?>
<?php include '_banner.php' ?>
<section class="section-main container">
  <div class="row">
    <form class="col-12" action="?action=save" method="post">
      <?php echo $wiki->getTitle() !== '' ? '<h1>' . htmlspecialchars($wiki->getTitle()) . '</h1>' : ''; ?>
      <input type="text" name="title" placeholder="<?php __('Title - may remain empty'); ?>" value="<?php echo $wiki->getTitle(); ?>">
      <textarea name="content" placeholder="<?php __('Content'); ?>" required><?php echo $wiki->getMarkup(); ?></textarea>
      <input type="text" name="author" placeholder="<?php __('Author'); ?>" value="<?php echo $user->getAlias(); ?>" required>
      <input type="submit" class="primary" value="<?php __('Save'); ?>"><input type="submit"
      value="<?php __('Save & Edit'); ?>"><a class="btn" href="<?php echo $wiki->getPath(); ?>"><?php __('Cancel'); ?></a>
    </form>
  </div>
</section>
<?php include '_footer.php' ?>
</body>
</html>
