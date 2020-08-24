# Page permissions

wiki.md can restrict access to page actions (create, view, edit, delete) for certain users on a (sub)folder level. This is done by setting page passwords. Site default is to let anyone view/read all pages, but only admin-users may create, edit or delete them. wiki.md knows the following permissions:

* `userCreate` - create pages
* `userRead` - view/read pages
* `userUpdate` - edit/update pages
* `userDelete` - delete pages

**Note:** It is currently not possible to edit permissions via the Wiki. You'll have to manually edit files in the filesystem.

## Permissions

To define permissions for a folder, create a file called `_.yaml` in it. The restrictions you define in it will be valid for that folder, as well as all its sub-folders. For example, take a look into the `data/content/_.yaml` file that wiki.md installs per default:

```
---
userCreate: admin
userRead: *
userUpdate: admin
userDelete: admin
```

This defines that, starting at the root, everyone (`*`) can read pages, but only an admin (`admin`) might create (`userCreate`), update (`userUpdate`) or delete (`userDelete`) pages. Since this definition is done at root-level, it applies to all the folder of your site.

Now look into `data/content/docs/_.yaml`:

```
---
userCreate: docs
userUpdate: docs
userDelete: docs
```

This defines, that in this folder - and all its sub-folders - the `docs` user might also create, edit and delete pages, in addition to the `admin` user defined at root level. Since there is no mention of `userRead` in this file, it derives that information from the root folder, resulting in everyone (`*`) still being able to view the pages.

If no `_.yaml` file exists in a directory, it derives all the permissions from the parent folder.

## Passwords

Passwords for users mentioned in the `_.yaml` files can be found in `data/.htpasswd`. They are stored in bcrypt format. To add a user/password, just add a new line. To change a password, enter the corresponding bcrypt hash in its line. You can use the `htpassd` provided by Apache's httpd package for that:

```
$ htpasswd -B /path/to/wiki/data/.htpasswd <username>
```
