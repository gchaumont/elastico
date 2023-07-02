<?php

namespace Elastico\Aggregations;

use Elastico\Query\Builder\HasAggregations;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Elastico\Query\Response\Collection;
use Illuminate\Support\Traits\Conditionable;

/**
 * Abstract Aggregation.
 */
abstract class Aggregation
{
    use Conditionable;
    use HasAggregations;

    public const TYPE = 'abstract';

    const RESPONSE_CLASS = AggregationResponse::class;

    public static function make(...$args): static
    {
        return new static(...$args);
    }

    abstract public function getPayload(): array;

    final public function compile(): array
    {
        return collect([
            static::TYPE => $this->getPayload() ?: new \stdClass(),
            'aggs' => $this->getAggregations()->map(fn ($aggregation) => $aggregation->compile())->all(),
        ])
            ->filter()
            ->all();
    }

    public function toResponse(array $response): AggregationResponse
    {
        return new (static::RESPONSE_CLASS)(
            aggregation: $this,
            response: $response,
        );
    }

    public function formatAggregationResult(array $data): array|Collection
    {
        return $data;
    }

    public function each(callable $callback): self
    {
        $this->getAggregations()->each(fn ($aggregation) => $callback($aggregation));

        return $this;
    }
}
