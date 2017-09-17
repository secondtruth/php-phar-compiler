PHP PHAR Compiler
=================

[![Latest Stable](http://img.shields.io/packagist/v/secondtruth/phar-compiler.svg)](https://packagist.org/p/secondtruth/phar-compiler)
[![Build Status](https://img.shields.io/travis/secondtruth/php-phar-compiler.svg)](https://travis-ci.org/secondtruth/php-phar-compiler)
[![Scrutinizer](http://img.shields.io/scrutinizer/g/secondtruth/php-phar-compiler.svg)](https://scrutinizer-ci.com/g/secondtruth/php-phar-compiler)
[![Coverage](http://img.shields.io/scrutinizer/coverage/g/secondtruth/php-phar-compiler.svg)](https://scrutinizer-ci.com/g/secondtruth/php-phar-compiler)
[![License](http://img.shields.io/packagist/l/secondtruth/phar-compiler.svg)](https://packagist.org/p/secondtruth/phar-compiler)

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

[Install Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx) if you don't already have it present on your system.

To install the library, run the following command and you will get the latest version:

    $ php composer.phar require secondtruth/phar-compiler


Requirements
------------

* You must have at least PHP version 5.4 installed on your system.
