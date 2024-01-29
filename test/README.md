# Unit Tests

Prerequisites:

* local PHP-cli installed

```
npm run dist
npm run test:unit
```

# Integration tests

Prerequisites:

* local PHP-cli installed
* php-curl extension installed
* running webserver serving dist/wiki.md at wiki.local (read+write permissions in dir!)

```
npm run package
npm run test:integration
```
