<?php

namespace Elastico\Query\FullText;

use Elastico\Query\Query;

/**
 * Elastic Match Query.
 */
class MatchPhraseQuery extends Query
{
    protected string $type = 'match_phrase';

    public function __construct(
        protected string $field,
        protected string $message
    ) {
    }

    public function getPayload(): array
    {
        return [
            $this->field => $this->message,
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
