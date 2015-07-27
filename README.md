PHP PHAR Compiler
=================

This library provides a generic PHP PHAR compiler.


How to use?
-----------

```php
$compiler = new Compiler(PROJECT_PATH);

$compiler->addIndexFile('bin/mycoolprogram.php');
$compiler->addDirectory('libraries');

$compiler->addFile('vendor/autoload.php');
$compiler->addDirectory('vendor/composer', '!*.php');
$compiler->addDirectory('vendor/.../Component/Console', ['Tests/*', '!*.php']);

$compiler->compile("$outputDir/mycoolprogram.phar");
```


Installation
------------

### Install via Composer

Create a file called `composer.json` in your project directory and put the following into it:

```
{
    "require": {
        "secondtruth/phar-compiler": "1.0.*"
    }
}
```

[Install Composer](https://getcomposer.org/doc/00-intro.md#installation-nix) if you don't already have it present on your system:

    $ curl -sS https://getcomposer.org/installer | php

Use Composer to [download the vendor libraries](https://getcomposer.org/doc/00-intro.md#using-composer) and generate the vendor/autoload.php file:

    $ php composer.phar install

Include the vendor autoloader and use the classes:

```php
namespace Acme\MyApplication;

use Secondtruth\Compiler\Compiler;

require_once 'vendor/autoload.php';
```


Requirements
------------

* You must have at least PHP version 5.4 installed on your system.
