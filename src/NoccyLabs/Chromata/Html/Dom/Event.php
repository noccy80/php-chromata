<?php

namespace NoccyLabs\Chromata\Html\Dom;

class Event
{
    private $type;

    private $data;

    private $target;

    private $stopped = false;

    public function __construct($target, $type, array $data)
    {
        $this->target = $target;
        $this->type = $type;
        $this->data = $data;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTarget()
    {
        return $this->target;
    }

    protected function stopPropagation()
    {
        $this->stopped = true;
    }

    public function isPropagationStopped()
    {
        return (bool)$this->stopped;
    }

    public function __get($key)
    {
        switch ($key) {
            case 'target': 
                return $this->getTarget();
            default:
                if (array_key_exists($key, $this->data)) {
                    return $this->data[$key];
                }
        }
        return null;
    }

}
