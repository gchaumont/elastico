<?php

namespace Gchaumont\Query;

/**
 * Elastic Exists Query.
 */
class MatchAll extends Query
{
    protected $type = 'match_all';

    protected ?int $boost = null;

    public function getPayload(): array
    {
        return array_filter([
            'boost' => $this->boost,
        ]);
    }

    public function boost(int $boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
