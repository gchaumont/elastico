<?php

namespace Elastico\Query;

use Elastico\Helpers\When;

/**
 * Elastic Base Query.
 */
abstract class Query
{
    use When;

    abstract public function getPayload(): array;

    final public function compile(): array
    {
        return [
            $this->type => $this->getPayload() ?: new \stdClass(), // ?: new \stdClass,
        ];
    }
}
