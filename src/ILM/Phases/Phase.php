<?php

namespace Elastico\ILM\Phases;

use Exception;


abstract class Phase
{
    public string $min_age;

    public function toArray(): array
    {
        if (empty($this->min_age)) {
            throw new Exception('min_age is required');
        }
        $array = [];
        foreach ($this as $key => $value) {
            if ($key === 'min_age') {
                $array[$key] = $value;
            } else {
                $array['actions'][$key] = $value?->toArray();
            }
        }
        $array['actions'] = array_filter($array['actions'] ?? []);
        if (empty($array['actions'])) {
            unset($array['actions']);
        }
        return $array;
    }
}
