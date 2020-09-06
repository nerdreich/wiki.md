# wiki.md Plugins

Plugins are a way to add functionalty to wiki.md. The following are shipped with the core and enabled by default:

* Macros (expansion of `{{}}` in markup)
* Media (Media upload & management UI)
* User (User & permission management UI)

## Install a 3rd party plugin

To add a plugin, follow the installation instructions that is bundled with it. This usually boils down to:

* Extract the `*.tar.gz`/`*.zip` of the plugin.
* Copy the plugin folder into `plugins/` of your wiki.md installation.
* Edit `data/config.ini` and add the plugin name to the `plugins` entry (comma-separated, case-sensitive).

## Create your own plugin

See [Creating plugins](plugins_create.md) how to make your own.
