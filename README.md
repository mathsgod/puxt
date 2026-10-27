# puxt

## Configuration

PUXT uses `PUXT\Config` for `$app->config`, shared with its Context and
ModuleContainer. `puxt.config.php` still returns an array. Nested property and
array access, mutable sections, `get()`, `merge()`, `toArray()`, iteration,
cloning and read-only behavior are retained from Laminas Config 3.11.0.

```php
$config = new \PUXT\Config(['VX' => ['enabled' => true]], true);
$config['VX']['enabled'] = false;
$enabled = $config->VX->enabled;
```

Code that explicitly imports or type-hints `Laminas\Config\Config` should use
`PUXT\Config` instead. Config exceptions now live in `PUXT\Config\Exception`.
Laminas Reader, Writer, Factory and Processor APIs are not included.
The derived files retain their BSD-3-Clause license; see
[Laminas Config attribution](licenses/laminas-config/README.md).

## Regression tests

Requires PHP 8.3 or later with the extensions required by PHPUnit.

```sh
composer install
php8.3 vendor/bin/phpunit
```

The tests create temporary applications and cover file routing, HTTP methods,
JSON requests/responses, context/request injection, Twig escaping and layouts,
HTML pages, page metadata, plugins, middleware, base paths and error responses.
Warnings, notices and deprecations fail the suite.

The same suite can be run with `php8.4` or `php8.5`.
The suite passes on PHP 8.3, 8.4 and 8.5 with League Route 7.0.
