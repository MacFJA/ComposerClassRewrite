<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 08/10/2015
 * Time: 22:46
 */

namespace MacFJA\Test;


class C extends A implements MacFJA\ClassRewrite\Rewriter
{
    public function who()
    {
        return parent::who() . '-C';
    }
}