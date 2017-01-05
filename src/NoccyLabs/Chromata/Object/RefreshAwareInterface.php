<?php

namespace NoccyLabs\Chromata\Object;

use SplObjectStorage;

interface RefreshAwareInterface
{
    public function refresh();
}