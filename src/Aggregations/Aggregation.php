<?php

namespace Elastico\Aggregations;

use App\Support\Traits\When;
use Elastico\Query\Builder;
use Elastico\Query\Builder\HasAggregations;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Elastico\Query\Response\Response;

/**
 * Abstract Aggregation.
 */
abstract class Aggregation
{
    use When;
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

    public function toResponse(array $response, Builder $query): AggregationResponse
    {
        return new (static::RESPONSE_CLASS)(
            aggregation: $this,
            response: $response,
            query: $query,
        );
    }

    public function formatAggregationResult(array $data, Builder $builder): array|Response
    {
        return $data;
    }

    public function each(callable $callback): self
    {
        $this->getAggregations()->each(fn ($aggregation) => $callback($aggregation));

        return $this;
    }
}
