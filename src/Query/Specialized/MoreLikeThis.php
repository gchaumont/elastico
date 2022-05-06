<?php

namespace Elastico\Query\Specialized;

use Elastico\Query\Query;

/**
 * Elastic MoreLikeThis Query.
 */
class MoreLikeThis extends Query
{
    protected $type = 'more_like_this';

    protected array $fields;

    protected int $min_freq_terms;

    protected int $max_query_terms;

    protected int $min_doc_freq;

    protected $like;

    public function getPayload(): array
    {
        $payload = [
            'like' => $this->like,
        ];

        foreach (['fields', 'min_ferq_terms', 'max_query_terms', 'min_doc_freq'] as $property) {
            if (isset($this->{$property})) {
                $payload[$property] = $this->{$property};
            }
        }

        return $payload;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function like($like): self
    {
        $this->like = $like;

        return $this;
    }

    public function minFreqTerms(int $minFreqTerms): self
    {
        $this->min_freq_terms = $minFreqTerms;

        return $this;
    }

    public function minDocFreq(int $minDocFreq): self
    {
        $this->min_doc_freq = $minDocFreq;

        return $this;
    }

    public function maxQueryTerms(int $maxQueryTerms): self
    {
        $this->max_query_terms = $maxQueryTerms;

        return $this;
    }
}
