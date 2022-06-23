<?php

namespace Elastico\Helpers;

trait When
{
    public function when(mixed $value, callable $callback, callable $alternateCallback = null): static
    {
        $value = is_callable($value) ? $value() : $value;

        if ($value) {
            $callback($this) ?: $this;
        }

        if ($alternateCallback) {
            $alternateCallback($this) ?: $this;
        }

        return $this;
    }
}
