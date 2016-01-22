<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 08/10/2015
 * Time: 22:46
 */

namespace MacFJA\Test;


class B extends A
{
    public function who()
    {
        return parent::who() . '-B';
    }
}