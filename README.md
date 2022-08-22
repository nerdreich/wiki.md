# wiki.md

Yet another Wiki/CMS written in PHP.

![wiki.md](preview.png)

## Key features

* File-based - no database required.
  * Every page is a single file in a folder tree.
  * Unlimited folder/grouping depth.
* [Markdown](https://en.wikipedia.org/wiki/Markdown) markup.
  * Including Markdown Extra support (e.g. tables).
* [YAML](https://en.wikipedia.org/wiki/YAML) front matter (YFM) metadata.
* Page versioning / history (udiff).
* Folder users/passwords.
  * Set separate permissions for create/read/edit/delete.
* Content inheritance
  * Reference partials (e.g. navigation or banner) in pages on the same/deeper directory levels.
* Themes
  * Ships with _Elegant_, a responsive default theme.
  * Custom theme support.
* Plugin support.
* GNU AGPL-3.0 licensed.

Due its file-based nature, wiki.md works best for small to medium traffic sites.

## Requirements

* PHP 7.2 / 7.3 / 7.4 / 8.0 / 8.1
* Apache `.htaccess` / `mod_rewrite`

## Supported Browsers

Any recent HTML5-capable browser should do.

## Installation

Extract the `*.tar.gz`/`*.zip` into a folder on your web-server and you are (almost) ready to go - wiki.md comes with reasonable, secure defaults. See [Docs](docs/README.md) for details.

## Build from source

This is only recommended for advanced use-cases. For most users the pre-packaged `*.tar.gz`/`*.zip` should be fine.

To build wiki.md yourself, you'll need `git`, `php` v7.2+, `npm` v8.0+ and `gulp` v5. Assuming all requirements are met, just:

```
git clone --depth 1 https://github.com/nerdreich/wiki.md
cd wiki.md
npm install
npm run gulp release
```

Afterwards, the archives can be found in the `dist/` folder.

## Next steps

Read the [Documentation](docs/) to learn more.

## Roadmap

Check out the [roadmap](docs/ROADMAP.md) for planned features.

Feel free to report any [issues](https://github.com/nerdreich/wiki.md/issues) you find.
