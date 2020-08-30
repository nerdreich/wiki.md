# Filter chains

Before wiki.md delivers content to the browser, it will go through a series of chains of filters to (post)process it. Themes can add their own filters to chains to expand functionality of the wiki.

The following chains exist:

* `raw` - Run on the raw content that was loaded from disk, before it is handed over to a markup parser. This is a good place to expand macros and inject dynamic content.
* `markup` - Run before markup is rendered into HTML. This is a good place to enhance the markup itself, e.g. to fix links or headlines.
* `html` - Run before the final HTML is delivered to the browser. This is a good place to manipulate or inject HTML markup.

Note: [Macros](macros.md) are not filters - but the the framework code that expands them is implemented as `raw` filter.

## Adding filters

To add a filter, add the following to your theme's `setup.php`:

```php
$wiki->registerFilter(
  'html',     // name of one of the filter chains
  'myFilter', // custom name of your filter
  function (string $content, string $path): string {
    $content = ... // manipulate the content here
    return $content;
  }
);
```

`$content` will contain the the current stage of the content, as processed by the previous filter in the chain. `return` it with your applied changes to forward it to the next filter in the chain.

`$path` will contain the full file-system path of the page/snippet/file this filter is processed on. This can be useful if the code needs to know the page's position in the wiki tree.
