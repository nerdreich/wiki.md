# v2.3.0

* added default_author config.ini variable
* added integration tests for all supported PHP versions
* fixed test:unit / missing docs in release zip/tgz
* updated screenshot

# v2.2.0

* moved to bootstrap 5.3
* moved from sass-lint to stylelint + prettier
* moved phpcs from gulp to npm
* moved to variable fonts (Noto Serif, Noto Sans Mono)

# v2.1.1

* fixed history squashing sometimes not working
* tweaked Elegant theme

# v2.1.0

* added PHP8 support
* added server setting to enable/disable embedded HTML in Markdown
* improved Elegant theme
  * changed font to Noto Serif
  * added blockquote styling
  * added definition list styling
  * tweaked headline styling
  * fixed list-in-list styling
  * upgraded to Bootstrap 5
* fixed missing value in macros
* bumped dependencies & updated to npm 8 / eslint 8

# v2.0.2

* fixed changelog default page title
* updated npm dependencies

# v2.0.1

* fixed url handling in subdirectory installations

# v2.0.0

* added filter/plugin mechanism
* added basic media manager
* added broken-links detection
* added simple/regular logins
* added flexible request router
* changed namespace to at/nerdreich/wiki
* changed macros into plugin
* changed media manager into plugin
* fixed multiple search/replace in filters
* fixed theme navbar colors
* improved string comparison performance
* removed datadir config entry, updated docs

# v1.0.0

* added folder permission screen
* added history entries for non-wiki.md saves of pages
* fixed include macro not finding the files
* improved dirty/hash warnings in editor

# v0.5.0

* added default value for empty history
* added CONTRIBUTING.md and cleaned up src dir
* added detection of empty diffs in page history
* added warning if someone else is editing a page
* changed history page layout to a timeline view
* removed PHP 7.4 dependencies
* use PHPUnit for integration tests

# v0.4.0

* added more documentation
* added page-path css classes
* added support for media files
* added burger icon
* added permission check & tests for {{include}}
* added integration tests
* added phplinter checks to gulpfile
* moved permission checks into Wiki class
* added more unit tests
* added phpunit & unit tests
* introduced core/ folder for main files
* fixed hash-wrong warning for yet unversioned pages
* changed theme favicon color
* added history auto-squashing
* added autofocus on primary form fields
* added default title for pages based on URL
* added preview image to README.md

# v0.3.0

* added Markdown quickhelp to edit page
* added page hashes to secure page history
* fixed history page
* added data/ folder for all site artefacts
* fixed navbar checkbox visibility
* moved favicon into theme

# v0.2.0

* added transparent folder/README handling
* added {{paginate}} theme macro
* fixed unnecessary markup preprocessing in editor
* added version/repo fields to wiki class
* fixed missing dot-files and file extensions in release files (.zip, .tar.gz)

# v0.1.0 - first public release

* initial version
