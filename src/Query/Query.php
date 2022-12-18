<?php

namespace Elastico\Query;

use Illuminate\Support\Traits\Conditionable;

/**
 * Elastic Base Query.
 */
abstract class Query
{
    use Conditionable;

    abstract public function getPayload(): array;

    final public function compile(): array
    {
        return [
            $this->type => $this->getPayload() ?: new \stdClass(), // ?: new \stdClass,
        ];
    }

    public static function make(): static
    {
        return new static();
    }
}
