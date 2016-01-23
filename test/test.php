<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 08/10/2015
 * Time: 22:48
 */

use MacFJA\Test\B;
use MacFJA\Test\D;

/** @var Composer\Autoload\ClassLoader $c */
$c = require_once __DIR__.'/../vendor/autoload.php';

$b = new B();
echo $b->who(); // Output: "A-C-B"
//                        (A=A-C)-B
$d = new D();
echo $d->who(); // Output: "A-C-C-D"
//                        (A=A-C)-C-D
