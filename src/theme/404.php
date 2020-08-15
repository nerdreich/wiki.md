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
  <title><?php __('Not found'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/site.webmanifest">
  <link rel="apple-touch-icon" href="/icon.png">

  <link rel="stylesheet" href="<?php echo $config['themePath']; ?>style.css?v=$VERSION$">
</head>
<body>
<?php include '_navbar.php' ?>
<?php include '_banner.php' ?>
<section class="section-main container">
  <div class="row">
    <div class="col-12">
      <p><?php __('Page %s does not exist.', $contentPath); ?></p>
      <?php if ($user->mayCreate($contentPath)) { ?>
        <form action="?action=createPage" method="post">
          <input type="submit" class="primary" value="<?php __('Create page'); ?>">
        </form>
      <?php } ?>
    </div>
  </div>
</section>
<?php include '_footer.php' ?>
</body>
</html>
