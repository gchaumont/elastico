<?php

namespace Elastico\Query\FullText;

use Elastico\Query\Query;

/**
 * Elastic Match Query.
 */
class MatchQuery extends Query
{
    protected string $type = 'match';

    protected string $field;

    protected string $string;

    public function getPayload(): array
    {
        return [
            $this->field => $this->query,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function query(string $query): self
    {
        $this->query = $query;

        return $this;
    }
}
