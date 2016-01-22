<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 08/10/2015
 * Time: 22:48
 */

use MacFJA\Test\B;

/** @var Composer\Autoload\ClassLoader $c */
$c = require_once __DIR__.'/../vendor/autoload.php';

$b = new B();
echo $b->who(); // Output: "A-C-B"