<?php

namespace Elastico\Query;

use Illuminate\Support\Traits\Conditionable;
use stdClass;

/**
 * Elastic Base Query.
 */
abstract class Query
{
    use Conditionable;

    protected string $type;

    abstract public function getPayload(): array;

    final public function compile(): array
    {
        $payload = $this->getPayload();

        return [
            $this->type => $this->getPayload() ?: new \stdClass(),
        ];
    }

    public static function make(...$args): static
    {
        return new static(...$args);
    }
}
