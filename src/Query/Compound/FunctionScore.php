<?php

namespace Elastico\Query\Compound;

use stdClass;
use Elastico\Query\Query;

/**
 * Function Score Query.
 */
class FunctionScore extends Query
{
    protected string $type = 'function_score';

    protected ?Query $query = null;

    protected ?float $boost = null;

    protected ?string $boost_mode = null;

    protected ?Script $script_score = null;

    protected ?float $weight = null;

    protected ?array $field_value_factor = null;

    protected ?array $decay = null;

    protected ?array $random_score = null;

    public function getPayload(): array
    {
        $payload = [];
        if (!is_null($this->query)) {
            $payload['query'] = $this->query->compile();
        }
        foreach (['boost', 'script_score', 'weight', 'field_value_factor', 'decay', 'random_score'] as $type) {
            if (!is_null($this->{$type})) {
                $payload[$type] = $this->{$type} ?: new stdClass();
            }
        }

        return $payload;
    }

    public function randomScore(?int $seed = null, ?string $field = null): self
    {
        $this->random_score = array_filter(func_get_args());

        return $this;
    }

    public function fieldValueFactor(?string $field, float $factor, string $modifier = null, float $missing = null): self
    {
        $this->field_value_factor = array_filter(func_get_args());
    }

    public function weight(float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }
}
