# wiki.md

Yet another Wiki/CMS written in PHP. Key features:

* File-based - no database required.
  * Every page is a single file in a folder tree.
  * Unlimited folder/grouping depth.
* [Markdown](https://en.wikipedia.org/wiki/Markdown) markup.
  * Including extended Markdown support (e.g. tables).
* [YAML](https://en.wikipedia.org/wiki/YAML) front matter (YFM) metadata.
* Page versioning / history (udiff).
* Folder passwords (separate permissions for create/read/update/delete).
* Snippet inheritance: Reference your partials (e.g. navigation or banner) in pages on the same/deeper directory levels.
* Elegant, responsive default theme.
* Custom theme support.
* GNU AGPL-3.0 licensed.

Due its file-based nature, wiki.md works best for small to medium traffic sites.

## Requirements

* PHP 7.1+
* Apache `.htaccess` / `mod_rewrite`

## Supported Browsers

Any recent HTML5-capable browser should do.

## Installation

Extract the `*.tar.gz`/`*.zip` into a folder on your webserver and you are (almost) ready to go - wiki.md comes with reasonable, secure defaults. See [Docs](docs/README.md) for details.

## Next steps

Read the [Documentation](docs/) to learn more.

## Roadmap

wiki.md currently is beta software. It should work mostly fine, but you might miss features or hit some bugs. Feel free to report any issues you find.

### Planned for v0.1.0 (first public release)

* Footer-line too long (margin/padding)

### Planned for v1.0.0

* permission check for {{include}}
* file uploads
* better page history browser
* squash history of multiple page saves in short (configurable) time
* unit tests for utility code

### Planned for v1.1.0

* plugin mechanism
* rename-page feature
* configure date/time format + timezone

### Unscheduled ideas

* individual user language (setting)
* RSS/feed for changes
* move-page feature
* generate phpdocs during build
* generate sassdocs during build
