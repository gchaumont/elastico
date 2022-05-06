<?php

namespace Elastico\Query\FullText;

use Elastico\Query\Query;

/**
 * Elastic Match Query.
 */
class MultiMatchQuery extends Query
{
    protected string $type = 'multi_match';

    protected string $query;

    protected array $fields;

    protected string $match_type;

    protected string $operator;

    protected string $string;

    protected string $analyser;

    protected string $fuzziness;

    public function getPayload(): array
    {
        return array_filter([
            'query' => $this->query,
            'fields' => $this->fields,
            'type' => $this->match_type ?? null,
            'operator' => $this->operator ?? null,
            'analyzer' => $this->analyser ?? null,
            'fuzziness' => $this->fuziness ?? null,
        ]);
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function type(string $type): self
    {
        $this->match_type = $type;

        return $this;
    }

    public function operator(string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function analyser(string $analyser): self
    {
        $this->analyser = $analyser;

        return $this;
    }

    public function query(string $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function fuzziness(string $fuzziness): self
    {
        $this->fuzziness = $fuzziness;

        return $this;
    }
}
