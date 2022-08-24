# Unit Tests

Prerequisites:

* local PHP-cli installed

```
gulp dist
tools/phpunit-9.phar test/unit
```

# Integration tests

Prerequisites:

* local PHP-cli installed
* php-curl extension installed
* running webserver serving dist/wiki.md at wiki.local (read+write permissions in dir!)

```
gulp clean && gulp dist
tools/phpunit-9.phar test/integration
```
