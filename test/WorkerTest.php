<?php

namespace MacFJA\Test;

use MacFJA\ClassRewrite\Worker;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    public function testRewriteClass()
    {
        $worker = new Worker();
        $worker->setCacheDir(__DIR__.'/cache');
        $worker->clearCache();
        $worker->rewriteClass(__DIR__.'/A.php', __DIR__.'/C.php');
    }

    public function testRebuildClass()
    {
        $worker = new Worker();

        echo $worker->rebuildClass(__DIR__.'/C.php', '__TOTO__', 'AZERTY');
    }
}