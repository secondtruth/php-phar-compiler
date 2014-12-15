PHP PHAR Compiler
=================

This library provides a generic PHP PHAR compiler.


How to use?
-----------

```php
use Secondtruth\Compiler\Compiler;

$compiler = new Compiler(PROJECT_PATH);

$compiler->addIndexFile('mycoolprogram.php');
$compiler->addDirectory('libraries');

$compiler->addFile('vendor/autoload.php');
$compiler->addDirectory('vendor/composer', '!*.php');
$compiler->addDirectory('vendor/symfony/console/Symfony/Component/Console', ['Tests/*', '!*.php']);

$compiler->compile("$outputDir/mycoolprogram.phar");
```


Requirements
------------

* You must have at least PHP version 5.4 installed on your system.
