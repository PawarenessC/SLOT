<?php

namespace pawarenessc\slot;

use pocketmine\scheduler\Task;

class CallbackTask extends Task
{

    private $callable, $args;

    public function __construct(callable $callable, array $args = [])
    {
        $this->callable = $callable;
        $this->args = $args;
    }

    public function onRun($tick)
    {
        call_user_func_array($this->callable, $this->args);
    }
}

