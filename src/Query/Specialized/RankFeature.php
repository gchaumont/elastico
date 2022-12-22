<?php

namespace Elastico\Query\Specialized;

use Elastico\Query\Query;

/**
 * Elastic MoreLikeThis Query.
 */
class RankFeature extends Query
{
    protected $type = 'rank_feature';

    protected string $field;

    protected int|float|null $boost = null;

    public function getPayload(): array
    {
        return [
            'field' => $this->field,
            'boost' => $this->boost,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function boost($boost): self
    {
        $this->boost = $boost;

        return $this;
    }
}
