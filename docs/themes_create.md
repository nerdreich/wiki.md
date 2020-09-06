# Creating themes

If you want to adjust a few details and maybe re-color wiki.md, using _Elegant_ is a good starting point. Copy the theme into a new folder and you are ready to start. wiki.md has no child-theme support.

However, if you want more control over the page layout and would like to write your own theme from scratch, you'll have to provide at least the following files:

```
_theme.php
403.php
404.php
delete.php
edit.php
error.php
history.php
login.php
plugin.php
view.php
```

Your theme files can assume the following global variables to be present when called:

* `$wiki` contains a `WikiUI` object. Call it to obtain things like a page's title or content.

`_theme.php` is called first by the core to bootstrap your theme. It might define helper methods and setup other global stuff your theme might need. `_theme.php` should not output any HTML markup and is _not_ in charge to load the other theme files - wiki.md's core will do that.

### Template files

Each of the `*.php` files mentioned above has to output a complete HTML page, starting with `<!doctype html>` and ending with `</html>`. Like **Elegant** you might want to use additional includes to output common elements like headers or navigation elements.

* `view.php` is the main template responsible to render a page.

* `edit.php` contains the page editor. When the user is done, it has to POST the fields `title`, `content` and `author` to `/path/to/page?action=save`.

* `history.php` is in charge of rendering the page's history. If a user would like to restore a certain version, the page should link to `/path/to/page?action=restore&version=xyz`, `xyz` being the version number (1 = first/oldest). Restoring a version actually opens the page editor, with a new version on top that reconstructs the version the user would like to revert to. Unless it is saved in this editor, nothing actually happens to the page.

* `delete.php` should display a warning that a given page is about to be deleted and POST to `/path/to/page?action=deleteOK` (fields don't matter) if the user confirms that.

* `login.php` is used when the user needs to login to continue. This can happen at any page if the requested action is protected by a password. The login page should respect the `login_simple` config value and POST the fields `username` / `password` back to the URL it was rendered by including the `?action=xyz` of the caller, adding a `auth=login` field.

* `plugin.php` is used when a plugin wants to output a page in the style of the theme. It should create the necessary page header/footer and then include a plugin's file in the content area of the page grid.

* `403.php`, `404.php` and `error.php` contain error messages for the user. The numeric files corresponding to their HTTP status codes. `error.php` is a generic fallback page that is shown when wiki.md does not know how to handle an error.

There is no logout page. Logout can happen at any page by adding `?auth=logout` to the URL. This transparently logs-out the user and redirects back to the non-authorized version of that page - which then might be again a login screen.
