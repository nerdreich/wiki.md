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
  <title><?php echo htmlspecialchars($wiki->getTitle()); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($wiki->getDescription()); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/site.webmanifest">
  <link rel="apple-touch-icon" href="/icon.png">

  <style>
  body { background-color: #2b3e50; color: rgba(255, 255, 255, 0.8); }
  </style>
  <link rel="stylesheet" href="/<?php echo $config['themePath']; ?>style.css?v=$VERSION$">
</head>
<body>
<?php include '_navbar.php' ?>
<?php include '_banner.php' ?>
<section class="section-main container">
  <div class="row">
    <div class="col-12 col-md-8 col-lg-9">
      <?php echo $wiki->getTitle() !== '' ? '<h1>' . htmlspecialchars($wiki->getTitle()) . '</h1>' : ''; ?>
      <?php echo $wiki->getContentHTML(); ?>
    </div>
    <nav class="col-12 col-md-4 col-lg-3 sidenav">
      <?php echo $wiki->getSnippetHTML('nav'); ?>
    </nav>
  </div>
</section>
<?php include '_footer.php' ?>
</body>
</html>
