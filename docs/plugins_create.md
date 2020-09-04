# Creating plugins

Plugins are a way to extend wiki.md with new functionality. Common use cases are:

* add new screens (e.g. a media manager)
* add new filters (e.g. macros)

A minimal plugin consists of only one file. Let's assume you want to create `MyPlugin`:

```
.../plugins/myplugin/plugin.php
```

You can add all additinal files that your plugin needs (e.g. templates) in that folder, too.

## Plugin structure

Your `plugin.php` should at the bare minimum contain the following:

```
<?php

namespace org\example;

class MyPlugin
{
  private $ui;

  public function __construct($ui)
  {
    $this->ui = $ui;
    // TODO: initialize plugin here
  }
}

$ui->wiki->registerPlugin('myplugin', new MyPlugin($ui));
```

`plugin.php` can assume that, when run, the framework will provide a `$ui` variable containing a reference to the currently running wiki. It's probably a good idea to store it in your plugin so you can access the UI, config and core later on. Don't forget the last line, as this will let the core know about your plugin.

## Routes

If you want your plugin to have a UI / run for certain pages, you'll have to register a route:

```
public function __construct($ui)
{
  $ui->registerActionRoute('myplugin', 'list', function ($ui) {
    $this->doSomething();
  });
}

private function doSomething()
{
  // TODO: implement
}
```

Now if someone would call `wiki.example.org/some/page?myplugin=list`, `doSomething()` would run. You probably will want to check for [permissions](permissions.md) before executing code.

## Menu items

If you want your plugin to be visible in wiki.md's menu, you can register a menu entry:

```
public function __construct($ui)
{
  if ($ui->user->mayRead($ui->wiki->getWikiPath())) {
    $ui->wiki->addMenuItem('myplugin=list', 'Awesome');
  }
}
```

This example will add a menu item `Awesome` that link to `?myplugin=list` on every page the current user has read permissions. You'll also have to register the corresponding route to your code (see above).

## Filters

If you want your plugin to hook into a [filter chain](filters.md) to change markup or HTML, do:

```
public function __construct($ui)
{
  $ui->wiki->registerFilter('markup', 'myFilter',
    function (string $content, string $path): string {
      $content = ... // TODO: manipulate the content here
      return $content;
    }
  );
}
```
