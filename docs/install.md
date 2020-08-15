# Installation

## Requirements

Before you install, make sure the following requirements are met:

* Apache httpd (`.htaccess` and `mod_rewrite` enabled)
* PHP 7.1+

## Basic installation

Download a `wiki.md-<version>.tar.gz` or `wiki.md-<version>.zip` and extract it to a temporary location. If you want your whole site to be the wiki, copy everything from `wiki.md/` into the root of your webserver. If you want to run the wiki only within a subdirectory, copy everything into a folder of your choice on the webserver, turning that into the wiki.

### Change password

The default installation requires a `admin` password to edit pages. There is no default password, so you can't edit anything until you set one.

To do so, replace the `admin`'s password in `.htpasswd` with a new one. You can use any tool that can create a bcrypt hash, e.g. Apache's `htpassd`:

```
$ htpasswd -B /path/to/wiki/.htpasswd admin
```

### Delete documentation

wiki.md adds it's documentation as /docs to your installation. You can safely delete this folder if you don't need it.

## Advanced configuration

After basic installation, you might want to do one or more of the following...

### Theme

wiki.md comes with a single basic theme. See [Themes](themes) page how to install a 3rd party theme or create your own.

### Language

To set the menu language, edit `.config.ini` and set the `language =` line. Currently supported are:

|Code|Language|
|----|--------|
|`de`|German  |
|`en`|English |

wiki.md only supports a single, site-wide language.

### Search engines

wiki.md instructs search engines to not index your content per default. If you want your content to be found, change `robots.txt`'s contents to:

```
User-agent: *
Disallow:
```

### Caching & performance

The default `.htaccess` does not do anything except enabling the page routing. For a more comprehensive configuration that provides more security, caching and page compression, you can replace it with `.htaccess-full`.

Be aware that `.htaccess-full` might or might not work out-of-the-box depending on your Apache httpd version and how your provider has configured it. You have been warned.

### More users / passwords

The default installation only knows a single `admin` user. See [Permissions](permissions) how to add more.

### Content folder

Per default all wiki content is stored in a `content/` subfolder. As this is not visible in the URL, it's probably is ok for you. In case you want to change the foldername, edit `.config.ini` and set `content_dir` to a name of your choice. You will also have to rename the original `content/` folder or create a set up a new one from scratch.
