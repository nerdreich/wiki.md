# Creating plugins

Plugins are a way to extend wiki.md with new functionality. Common use cases are:

* add new screens (e.g. a media manager)
* add new filters (e.g. macros)

A minimal plugin consists of only one file. Let's assume you want to create `MyPlugin`:

```
.../plugins/myplugin/_plugin.php
```

You can add all additinal files that your plugin needs (e.g. templates) in that folder, too.

## Plugin structure

Your `_plugin.php` should at the bare minimum contain the following:

```
<?php

namespace org\example;

class MyPlugin extends \at\nerdreich\wiki\WikiPlugin
{
  public function setup();
  {
    // TODO: initialize plugin here
  }
}

$GLOBALS['wiki.md-plugins']['myplugin'] = '\org\example\MyPlugin';
```

Your plugin can assume that it will have access to `$wiki`/`$core`/`$config`/`$user` via it's abstract parent class (`Plugin`). Don't forget the last line - this will let the core know about your plugin.

## Routes

If you want your plugin to have a UI / run for certain pages, you'll have to register a route:

```
public function setup()
{
  $this->wiki->registerActionRoute('myplugin', 'list', function () {
    $this->doSomething();
  });
}

private function doSomething()
{
  // TODO: implement
}
```

Now if someone would call `wiki.example.org/some/page?myplugin=list`, `doSomething()` would run. You'll probably want to check for [user permissions](permissions.md) before executing code.

## Menu items

If you want your plugin to be visible in wiki.md's menu, you can register a menu entry:

```
public function setup()
{
  if ($this->core->mayReadPath()) {
    $this->wiki->addMenuItem('myplugin=list', 'Awesome');
  }
}
```

This example will add a menu item `Awesome` that link to `?myplugin=list` on every page the current user has read permissions. You'll also have to register the corresponding route to your code (see above).

## Filters

If you want your plugin to hook into a [filter chain](filters.md) to change markup or HTML, do:

```
public function setup()
{
  $this->core->registerFilter('markup', 'myFilter',
    function (string $content, string $path): string {
      // TODO: manipulate $content here
      return $content;
    }
  );
}
```
