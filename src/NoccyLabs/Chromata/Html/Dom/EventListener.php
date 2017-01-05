<?php

namespace NoccyLabs\Chromata\Html\Dom;

class EventListener
{
    private $type;

    private $token;

    private $options;

    private $handler;

    private $target;

    private $expired = false;

    public function __construct($type, callable $handler, array $options, $target)
    {
        $defaults = [
            'capture' => false,
            'once' => false,
            'passive' => false,
        ];
        $this->options = (object)array_merge($defaults, $options);
        $this->type = $type;
        $this->handler = $handler;
        $this->target = $target;
        $this->token = uniqid("ev_");
    }

    public function onEvent(Event $event)
    {
        assert('$this->expired == false');
        $handler = \Closure::bind($this->handler, $this->target, $this->target);
        call_user_func($handler, $event);
        if ($this->options->once) {
            $this->expired = true;
        }
    }

    public function getType()
    {
        return $this->type;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function canDiscard()
    {
        return $this->expired;
    }
}
