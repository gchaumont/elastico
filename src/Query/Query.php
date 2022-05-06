<?php

namespace Gchaumont\Query;

use App\Support\Traits\When;

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
