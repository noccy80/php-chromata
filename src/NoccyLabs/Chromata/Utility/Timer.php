<?php

namespace NoccyLabs\Chromata\Utility;

use NoccyLabs\Chromata\Object\RefreshAwareInterface;

class Timer implements RefreshAwareInterface
{
    const TIMER_ONE_SHOT = 0;
    const TIMER_INTERVAL = 1;

    protected $type;

    protected $func;

    protected $interval;

    protected $next;

    public function __construct(callable $func, $interval, $type=self::TIMER_INTERVAL)
    {
        $this->interval = $interval/1000;
        $this->type = $type;
        $this->func = $func;
        $this->next = microtime(true)+($this->interval);
    }

    public function refresh()
    {
        $mt = microtime(true);
        if ($mt >= $this->next) {
            call_user_func($this->func);
            $this->next = $mt + $this->interval;
            if ($this->type == self::TIMER_ONE_SHOT) {
                return false;
            }
        }
    }
}