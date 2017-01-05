<?php

namespace NoccyLabs\Chromata\Html\Script;


class Fragment
{
    protected $parts = [];

    public function add($fmt,...$vals)
    {
        if (count($vals)==0) {
            $this->parts[] = $fmt;
            return $this;
        }
        $svals = [];
        foreach ($vals as $val) {
            if (is_array($val)) {
                $val = json_encode($val);
            } elseif ($val === null) {
                $val = "null";
            } elseif (is_numeric($val)) {
                $val = intval($val);
            } elseif (is_bool($val)) {
                $val = ($val)?'true':'false';
            } else {
                $val = '"'.htmlspecialchars($val).'"';
            }
            $svals[] = $val;
        }
        $this->parts[] = sprintf($fmt, ...$svals);
        return $this;
    }

    public function getScript()
    {
        return sprintf("(function(){%s}());", join("",$this->parts));
    }

    public function __toString()
    {
        return $this->getScript();
    }
}