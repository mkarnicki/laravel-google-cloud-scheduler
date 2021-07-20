<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

class NullScheduledCommand
{
    public $withoutOverlapping = false;

    public function callBeforeCallbacks($container)
    {
        //
    }

    public function callAfterCallbacks($container)
    {
        //
    }
}