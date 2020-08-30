# Creating themes

If you want to adjust a few details and maybe re-color wiki.md, using **Elegant** is a good starting point. Copy the theme into a new folder and you are ready to start. wiki.md has no child-theme support.

However, if you want more control over the page layout and would like to write your own theme from scratch, you'll have to provide at least the following files:

```
setup.php
403.php
404.php
admin_folder.php
delete.php
edit.php
error.php
history.php
login.php
view.php
```

Your theme files can assume the following global variables to be present when called:

* `$wiki` contains a `Wiki` object. Call it to obtain things like a page's title or content.

* `$user` contains a `UserSession` object. Call if your theme has to figure out if the current user may or may not do something.

* `$config` contains an array with all configuration settings from `data/config.ini`. You may for example query it to find out the language that is currently used.

`setup.php` is called first by the core to bootstrap your theme. It might define helper methods and setup other global stuff your theme might need. `setup.php` should not output any HTML markup and is _not_ in charge to load the other theme files - wiki.md's core will do that.

### Template files

Each of the `*.php` files mentioned below has to output a complete HTML page, starting with `<!doctype html>` and ending with `</html>`. Like **Elegant** you might want to use additional includes to output common elements like headers or navigation elements.

* `view.php` is the main template responsible to render a page.

* `edit.php` contains the page editor. When the user is done, it has to POST the fields `title`, `content` and `author` to `/path/to/page?action=save`.

* `history.php` is in charge of rendering the page's history. If a user would like to restore a certain version, the page should link to `/path/to/page?action=restore&version=xyz`, `xyz` being the version number (1 = first/oldest). Restoring a version actually opens the page editor, with a new version on top that reconstructs the version the user would like to revert to. Unless it is saved in this editor, nothing actually happens to the page.

* `delete.php` should display a warning that a given page is about to be deleted and POST to `/path/to/page?action=deleteOK` (fields don't matter) if the user confirms that.

* `403.php`, `404.php` and `error.php` contain error messages for the user. The numeric files corresponding to their HTTP status codes. `error.php` is a generic fallback page that is shown when wiki.md does not know how to handle an error.

* `login.php` is used when the user needs to login to continue. This can happen at any page if the requested action is protected by a password. The login page should respect the `login_simple` config value and POST the fields ùsername` / `password` back to the URL it was rendered by including the `?action=xyz` of the caller, adding a `auth=login` field.

* `admin_folder.php` is used when the admin wants to change folder/user settings. This page contains two independent forms, one to update permissions and one to add/update users.

There is no logout page. Logout can happen at any page by adding `?auth=logout` to the URL. This transparently logs-out the user and redirects back to the non-authorized version of that page - which then might be again a login screen.
