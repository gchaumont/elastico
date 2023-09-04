<?php

namespace Elastico\Query\Joining;

use Elastico\Query\Query;

/**
 * Elastic Exists Query.
 */
class Nested extends Query
{
    protected string $type = 'nested';

    public function __construct(
        protected string $path,
        protected Query $query,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'path' => $this->path,
            'query' => $this->query->compile(),
        ];
    }

    public function query(Query $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }
}
