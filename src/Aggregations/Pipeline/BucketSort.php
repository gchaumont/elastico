<?php

namespace Elastico\Aggregations\Pipeline;

use Elastico\Aggregations\Aggregation;

/**
 * BucketSort Aggregation.
 */
class BucketSort extends Aggregation
{
    public const TYPE = 'bucket_sort';


    public function __construct(
        public int $size = 10,
        public array $sort,
        public int $from = 0,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'size' => $this->size,
            'from' => $this->from,
            'sort' => $this->sort,
        ];
    }

    public function sort(array $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function from(int $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }
}
