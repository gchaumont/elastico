<?php

namespace Elastico\ILM\Actions;


/**
 * ILM Actions that can be performed on a phase
 */
abstract class Action
{
    public function toArray(): array
    {
        $array = [];
        foreach ($this as $key => $value) {
            $array[$key] = $value;
        }
        return $array;
    }
}
