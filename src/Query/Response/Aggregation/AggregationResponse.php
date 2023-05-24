<?php

namespace Elastico\Query\Response\Aggregation;

use ArrayAccess;
use RuntimeException;
use Illuminate\Support\Collection;
use Elastico\Query\Response\Response;
use Elastico\Aggregations\Aggregation;

/**
 *  Aggregation Response.
 */
class AggregationResponse implements ArrayAccess
{
    protected Collection $aggregations;

    final public function __construct(
        protected readonly Aggregation $aggregation,
        protected array|Response $response,
    ) {
        $this->response = $this->aggregation->formatAggregationResult($this->response);
    }

    public function aggregations(): Collection
    {
        return $this->aggregations ??= $this->aggregation->getAggregations()
            ->map(fn ($aggregation) => $aggregation->toResponse(response: $this->get($aggregation->getName())));
    }

    public function aggregation(string $key): null|self
    {
        return $this->aggregations()->get($key);
    }

    public function response(): array
    {
        return $this->response;
    }

    public function getAggregation(): Aggregation
    {
        return $this->aggregation;
    }

    public function get(string $key): mixed
    {
        return $this->response()[$key];
    }

    // For Single Value Metrics Aggregations
    public function value(): mixed
    {
        return $this->get('value');
    }

    public function collect(string $key): Collection
    {
        return Collection::make($this->get($key));
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->response);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Aggregation results shall not be modified');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Aggregation results shall not be modified');
    }

    public function dd(): never
    {
        if (request()->expectsJson()) {
            response($this->response())->send();
        }
        dd($this);
    }
}
