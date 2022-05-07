<?php

namespace Elastico\Helpers;

trait When
{
    public function when(mixed $value, callable $callback, callable $alternateCallback = null): static
    {
        $value = is_callable($value) ? $value() : $value;

        if ($value) {
            return $callback($this) ?: $this;
        }

        if ($alternateCallback) {
            return $alternateCallback($this) ?: $this;
        }

        return $this;
    }
}
