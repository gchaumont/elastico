<?php

namespace Elastico\Query\Builder;

trait HandlesSorts
{
    protected ?array $sort = null;

    public function latest(string $field = 'updated_at'): self
    {
        return $this->sort(by: $field, order: 'desc');
    }

    public function oldest(string $field = 'updated_at'): self
    {
        return $this->sort($field, 'asc');
    }

    public function sort(
        string $by,
        string $order = 'asc',
        string $missing = null,
        string $mode = null,
        array $nested = null
    ): self {
        if (!in_array($order, ['asc', 'desc'])) {
            throw new Exception('Invalid sort order (desc, asc)', 1);
        }
        $this->sort[] = compact('by', 'order', 'missing', 'mode', 'nested');

        return $this;
    }

    public function orderBy(
        string $by,
        string $order = 'asc',
        string $missing = null,
        string $mode = null,
        array $nested = null
    ): self {
        $this->sort($by, $order, $missing, $mode, $nested);

        return $this;
    }
}
