# Markdown

wiki.md is powered by [Markdown](https://en.wikipedia.org/wiki/Markdown), a simple yet efficient markup language.

## Text

Use `_italic_` for _italic_ text, `**strong**` for **strong** text, `~~strike through~~` for ~~strike through~~ text. To force a underscore or asterisk when Markdown would convert it, escape it with a backslash: `\*` for a \* and `\_` for a \_.

To create a new paragraph, enter an empty line before it.

To force a line break··  
enter two spaces··  
at the end of the line.

## Headlines

Use `#`, `##` or `###` at the beginning of a line to turn it into a headline of the corresponding level. Note that wiki.md will always insert the main headline (`<h1>`) using the page title/name. Headlines you set in your document will be rendered by the theme one level deeper (e.g. `#` -> `<h2>`).

## Monospace

Use `` `monospace` `` for `monospace` text inside a paragraph. Use

````
```
Monospace
Paragraph
```
````

to create

```
Monospace
Paragraph
```

## Links & new pages

Use `[an absolute link](/path/to/file)` to create [an absolute link](/path/to/file) and `[a relative link](another/pat/to/file)` to create [a relative link](another/pat/to/file). No new page will be created by linking to a non-existing page - but as soon as you follow the link, you will have the opportunity to do so.

If you use `[external link](https://example.org/)` to place an [external link](https://example.org/), it will automatically be marked with a small arrow.

## Tables

wiki.md supports Markdown Extra styled tables:

```
| Column A    | Column B    |
| ----------- | ----------- |
| Row 1.1     | Row 1.2     |
| Row 2.1     | Row 2.2     |
```
