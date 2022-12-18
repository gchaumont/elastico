<?php

namespace Elastico\Aggregations;

use Elastico\Query\Builder\HasAggregations;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Elastico\Query\Response\Response;
use Illuminate\Support\Traits\Conditionable;

/**
 * Abstract Aggregation.
 */
abstract class Aggregation
{
    use Conditionable;
    use HasAggregations;

    const RESPONSE_CLASS = AggregationResponse::class;

    public string $type;

    public function __construct(protected string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function getPayload(): array;

    final public function compile(): array
    {
        $this->build();

        $payload = [
            $this->type => $this->getPayload() ?: new \stdClass(),
        ];
        foreach ($this->getAggregations() as $name => $subAggregation) {
            $payload['aggs'][$name] = $subAggregation->compile();
        }

        return $payload;
    }

    public function build(): void
    {
    }

    public function toResponse(array $response): AggregationResponse
    {
        return new (static::RESPONSE_CLASS)(
            aggregation: $this,
            response: $response,
        );
    }

    public function formatAggregationResult(array $data): array|Response
    {
        return $data;
    }

    public function each(callable $callback): self
    {
        $this->getAggregations()->each(fn ($aggregation) => $callback($aggregation));

        return $this;
    }
}
