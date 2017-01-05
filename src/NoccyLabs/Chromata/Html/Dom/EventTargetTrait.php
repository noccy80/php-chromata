<?php

namespace NoccyLabs\Chromata\Html\Dom;

trait EventTargetTrait
{
    private $_listeners = [];

    public function addEventListener($type, callable $handler, array $options=[])
    {
        $listener = new Eventlistener($type, $handler, $options, $this);
        $this->pushListener($listener);
        return $this;
    }

    private function pushListener(EventListener $listener)
    {
        $type = $listener->getType();
        if (!array_key_exists($type, $this->_listeners)) {
            $this->_listeners[$type] = [ $listener ];
        } else {
            $this->_listeners[$type][] = $listener;
        }
        $this->updateEventListener($listener);
    }

    public function onEvent(Event $event)
    {
        $type = $event->getType();
        if (!array_key_exists($type, $this->_listeners)) {
            return;
        }
        foreach ($this->_listeners[$type] as $listener) {
            $listener->onEvent($event);
            if ($event->isPropagationStopped()) {
                return;
            }
        }
    }

}
