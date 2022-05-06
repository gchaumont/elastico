<?php

namespace Gchaumont\Query\Results;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 *  Elasticsearch Hit Collection.
 */
class HitCollection implements ArrayAccess, IteratorAggregate, Countable
{
    public function __construct(
        protected array $hits,
        protected int $total,
    ) {
    }

    public function total()
    {
        return $this->total;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hits);
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->hits[] = $value;
        } else {
            $this->hits[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->hits[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->hits[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->hits[$offset]) ? $this->hits[$offset] : null;
    }

    public function map(callable $callback)
    {
        return array_map($callback, $this->hits);
    }

    public function transform(callable $callback): static
    {
        $this->hits = $this->map($callback);

        return $this;
    }

    public function filter(callable $callback): static
    {
        $this->hits = array_filter($this->hits, $callback);

        return $this;
    }

    public function all(): array
    {
        return $this->hits;
    }

    public function count(): int
    {
        return count($this->hits);
    }

    public function first(): null|object
    {
        return $this->hits[0] ?? null;
    }
}
