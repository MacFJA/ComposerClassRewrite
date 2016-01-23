# Composer Class-Rewrite

[![Latest Version](https://img.shields.io/github/release/macfja/composer-class-rewrite.svg)](https://github.com/macfja/composer-class-rewrite/releases)
[![Software License](https://img.shields.io/packagist/l/macfja/composer-class-rewrite.svg)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/macfja/composer-class-rewrite.svg)](https://packagist.org/packages/macfja/composer-class-rewrite)

## What is Composer Class-Rewrite

Composer Class-Rewrite is a [Composer](https://getcomposer.org) plugin that allow you to rewrite almost[^1] any classes of your project.

[^1]: See the [**Limitations**](#limitations) section

## Principle[^2]

The idea is to scan every classes of the project to find classes declared as a rewrite. Then we make some modification on the parent (rewritten) class (_a copy of the parent class_) and the rewriter class (_a copy of the rewriter class_) and finally add them to the Composer autoload (before **PSR-0** and **PSR-4** classes).
So, when a class ask for the rewritten class, Composer will return our modified rewiter class.

[^2]: For more information on how it works, see the [**How it works**](#how-it-works) section

## Installation

With [Composer](https://getcomposer.org):
```sh
$ composer require macfja/composer-class-rewrite
```

## Usage

```php
# File:  example/A.php
namespace Example;
class A
{
    public function who()
    {
    	return 'A';
    }
}
```
```php
# File:  example/B.php
namespace Example;
class B extends A
{
    public function who()
    {
    	return parent::who() . '-B';
    }
}
```
```php
# File:  example/C.php
namespace Example;
class C extends A implements \MacFJA\ClassRewrite\Rewriter
{
    public function who()
    {
    	return parent::who() . '-C';
    }
}
```
```sh
$ composer dump-autoload # Mandatory after any changes in Example\A or in Example\C
```
```php
$b = new B();
echo $b->who(); // Output: "A-C-B"
```


## Limitations<a name="limitations"></a>

- Only work for **PSR-0** and **PSR-4** namespace.
- Only work if Composer is the first autoloader.
- Only work on non-dev dependency.
- Only work on class (not on trait, nor on interface).
- The rewriter class **MUST** have the same namespace than the rewritten class.
- You have to rebuild the autoload for every rewriter/rewritten changes.
- Have some side effects _(see example below)_
  - Class context is changed (magic constants).
  - Multiple call if you instanciate the rewriter class.


### Side effects

#### Multiple call

```php
# File:  example/A.php
namespace Example;
class A
{
    public function who()
    {
    	return 'A';
    }
}
````
```php
# File:  example/B.php
namespace Example;
class B extends A implements \MacFJA\ClassRewrite\Rewriter
{
    public function who()
    {
    	return parent::who() . '-B';
    }
}
```
```php
# File:  example/C.php
namespace Example;
class C extends B
{
    public function who()
    {
    	return parent::who() . '-C';
    }
}
````
```php
# File: test.php
require 'vendor/autoload.php';
$class = new A();
echo $class->who(); //output A-B

$class2 = new B();
echo $class2->who(); // output A-B-B
// -> the output is (A='A-B', parent of B) + '-B' (from B)

$class3 = new C();
echo $class3->who(); // output A-B-B-C
// -> the output is (A='A-B', parent of B) + '-B' (from B, parent of C) + '-C' (from C)
```

#### Magic constants

```php
# File:  example/A.php
<?php
namespace Example;
class A
{
    public function dir()
    {
    	return __DIR__; // "~/example"
    }
    public function thisClass()
    {
    	return __CLASS__; // "Example\A"
    }
}
````
```php
# File:  example/B.php
<?php
namespace Example;
class B extends A implements \MacFJA\ClassRewrite\Rewriter
{
    public function dir()
    {
    	return __DIR__; // "~/example"
    }
}
````

```php
# File: test.php
require 'vendor/autoload.php';
$class = new A();
echo $class->dir(); //output "~/vendor/_rewrite"
echo $class->thisClass(); //output "Example\Cc09c111b433d2b65b9b01c999ae6480874b076a8"
echo get_class($class); //output "Example\A"
```


## Explored ideas

1. Inject my code during the Composer autoloading. _**Issue:** Composer don't provide event in autoloader._
2. Change the Composer autoloader code to add my logic. _**Issue:** Change core code. So if Composer change it, he have to change mine too. Hard to maintain._
3. Prepend a customer autoloader before Composer autoloader. _**Issue:** Loose all the power of Composer autloading._

## Under the hood

This plugin use:

- `composer-plugin-api` (obvious)
- `nikic/php-parser`: For parsing php class, and rewrite them

## How it works<a name="how-is-works"></a>

Just before Composer autoloader generation, the plugin is parsing every **PSR-0** and **PSR-4** namespace. It search for class that implement the interface `\MacFJA\ClassRewrite\Rewriter`, store in memory the the rewriter class and the rewritten class. 
When the plugin have parse every classes. It rebuild every Rewriter and Rewritten class for rename the Rewritten classname with a hard to guess class name (in fact, it's a **sha1** of the source file) and to rename the Rewriter classname into the original Rewritten classname.
Finally it add the rewrite destination directory into Composer classmap autoload.  
Then it let Composer do it's stuff.

It's works because Composer start searching class in the classmap autoload.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.