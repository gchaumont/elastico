<?php

namespace Gchaumont\Query\Joining;

use Gchaumont\Query\Query;

/**
 * Elastic Exists Query.
 */
class Nested extends Query
{
    protected $type = 'nested';

    protected string $path;

    protected Query $query;

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
