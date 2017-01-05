<?php

namespace NoccyLabs\Chromata\App;

use NoccyLabs\Chromata\Html\Dom\Element;

class DomFactory
{

    public static function __callStatic($name, $args)
    {
        $args = (count($args)>0)?$args[0]:[];
        $id = (array_key_exists('id',$args))?$args['id']:null;
        $attr = (array_key_exists('attr',$args))?$args['attr']:null;
        $value = (array_key_exists('value',$args))?$args['value']:null;
        return new Element($name, $value, $attr, $id);
    }

}