# Unit Tests

Prerequisites:

* local PHP-cli installed

```
npm run dist && npm run test:unit
```

# Integration tests

Prerequisites:

* local PHP-cli installed
* php-curl extension installed
* running webserver serving dist/wiki.md at wiki??.localhost (?? = 74..85, read+write permissions in dir!)

```
npm run package && npm run test:integration:83
```

Alternative versions are `:74`, `:80`, `:81`, `:82`, `:83`, `:84` and `:85`.
