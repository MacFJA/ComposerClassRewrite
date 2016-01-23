<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 23/01/2016
 * Time: 19:17
 */

namespace MacFJA\Test;


class D extends C
{
    public function who()
    {
        return parent::who() . '-D';
    }
}