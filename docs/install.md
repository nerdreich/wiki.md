# Requirements

Before installing, make sure the following requirements are met:

* Apache httpd (`.htaccess` and `mod_rewrite` enabled)
* PHP 7.2+

# Basic installation

Download a `wiki.md-<version>.tar.gz` or `wiki.md-<version>.zip` from the [GitHub releases page](https://github.com/nerdreich/wiki.md/releases) and extract it to a temporary location.

If you want to turn your *whole site* into a wiki, copy everything from `wiki.md/` into the root of your web-server.

If you want to turn only a *sub-directory* into a wiki (let's assume `/my/wiki`), then edit `.htaccess` and change

```
RewriteRule ^(.*)$ /index.php?path=$1 [NC,L,QSA]
```

to

```
RewriteRule ^(.*)$ /my/wiki/index.php?path=$1 [NC,L,QSA]
```

before copying everything into the `my/wiki` folder on your web-server.

## Change password

The default installation requires an `admin` password to edit pages. There is no default password, so you can't edit anything until you set one.

To do so, replace the `admin`'s password in `.htpasswd` with a new one. You can use any tool that can create a bcrypt hash, e.g. Apache's `htpassd`:

```sh
$ htpasswd -B /path/to/wiki/data/.htpasswd admin
```

Once you have manually set an admin-password, you can login to wiki.md and use the [Permission Editor](permissions.md) from now on.

## Delete documentation

wiki.md adds its documentation as /docs to your installation. You can safely delete this folder if you don't need it.

# Upgrading

This section lists breaking changes.

## From v1.x.x to v2.x.x

* The names of most permissions have changed. See [permissions](permissions.md) and change the values in your `data/content/**/_.yaml` files accordingly.
* If you do not use the default theme (_Elegant_), you need to upgrade your theme.

## From earlier versions

No direct upgrade path is available. Please update to an interim version first.

# Advanced configuration

After basic installation, you might want to do one or more of the following...

## Theme

wiki.md comes with a single basic theme. See [Themes](themes.md) page how to install a 3rd party theme or create your own.

## Language

To set the menu language, edit `data/config.ini` and set the `language =` and `datetime =` lines. The default theme supports:

|Language|code|datetime   |
|--------|----|-----------|
|German  |de  |d.m.Y H:i  |
|English |en  |m/d/Y H:i a|

wiki.md only supports a single, site-wide language.

## Search engines

wiki.md instructs search engines to **not** index your content per default. If you want your content to be found, change `robots.txt`'s contents to:

```
User-agent: *
Disallow:
```

## Caching & performance

The default `.htaccess` does not do anything except enabling the page routing. For a more comprehensive configuration that provides more security, caching and page compression, you can replace it with `.htaccess-full`.

Be aware that `.htaccess-full` might or might not work out-of-the-box depending on your Apache httpd version and how your provider has configured it. You have been warned.

## More users / passwords

The default installation only knows a single `admin` user. See [Permissions](permissions.md) how to add more.

## History auto-squashing

When users save pages very often in short intervals, they can fill up the page history quickly with minor versions. History squashing will detect repeated saves within a defined time intervall and combine those minor saves into one history entry. Per default, saves of the same page from the same author within 120 seconds will be auto-squashed. You can change this interval by editing `data/config.ini`:

```
autosquash_interval = 120 ; seconds
```

A value of `-1` disables auto-squashing.

## Editor warnings

When users open a page editor that another user/session has opened and have not saved yet, wiki.md will show a warning. Since users occasionally just don't save, you can set the interval this warning keeps showing up in your `config.ini` file:

```
edit_warning_interval = 900 ; seconds
```
