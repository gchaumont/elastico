<?php

namespace Elastico\Query\FullText;

use Elastico\Query\Query;

/**
 * Elastic Match Query.
 */
class MatchPhraseQuery extends Query
{
    protected $type = 'match_phrase';

    protected string $field;

    protected string $message;

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

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }
}
