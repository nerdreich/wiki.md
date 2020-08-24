# Macros

wiki.md and it's themes can provide macros to enrich your content / Markdown files. Before outputting a page, wiki.md will replace known macros with dynamic content. Macros have the following basic syntax:

```
{{name [primary] [| key = value] [| key2 = value2] [| ...] }}
```

Depending on the macro, `primary` or key/value pairs might or might not be present. So you might encounter `{​{name}​}` or `{​{name param}​}` in the wild. Complex calls can be split in multiple lines if you prefer:

```
{{name primary
  | key = value
  | key2 = value2
}}
```

## \{\{include\}\}

This macro allows to include other pages in a page:

```
{​{include <page>}​}
```

`include` supports absolute and relative wiki paths and will check permissions before embedding the content. Examples:

```
{​{include lion}​}
{​{include ../plant/rose}​}
{​{include /animal/lion}​}
```

`include` will not check for cyclic includes, which will result in an server error.
