# Contributing

This project is covered by the [GNU AGPL-3.0](LICENSE.md). Anything you contribute automatically falls under that license, too.

When contributing code, especially when adding new features, please first propose the change you wish to make via a [GitHub issue](https://github.com/nerdreich/wiki.md/issues). This helps to avoid misunderstandings or duplicate effort.

## Help wanted

This project could need help in the following areas:

* Testing and filing of bug reports ([issues](https://github.com/nerdreich/wiki.md/issues)).
* Translations (see src/theme/I18N folder).

## Pull Requests

Please submit changes via pull requests.

* One pull request per fix or feature or issue.
* Each commits should focus on one task.
* Use meaningful commit messages. In general, they should start with `added .. `, `changed ...`, `fixed ...` or `removed ...` and describe what happened to the code.
* Development is done on the `develop` branch. Create a feature branch from there and request to pull back into it.
* Make sure `gulp dist` runs without errors or warnings. This will enforce our coding standards.

## Coding standards

All project files use UTF-8 encoding and Unix-style line endings. We use `gulp` as build tool and `npm` as dependency tool.

### Project layout

```
/
/data    # default/template wiki content
/dist    # generated release files (not in git)
/docs    # documenation for admins and users
/src
  /core  # source code of wiki.md's core
  /theme # source code of Elegant, the default theme
/test    # (unit) testing code for src
/tools   # misc. helper code and tools
```

### PHP

* All PHP code must adhere the [PSR12 coding standard](https://www.php-fig.org/psr/psr-12/). Gulp will check & enforce that by using a linter.

### (S)CSS

* All styles are written in [SCSS](https://sass-lang.com/).
* We follow the [7-1 pattern](https://sass-guidelin.es/#the-7-1-pattern) for naming sass files.
* We use the `sass-lint` coding standard, with a few exception defined in `.sass-lint.yml`. Gulp will check & enforce that via a plugin.
