<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Terms Aggregation.
 */
class Nested extends BucketAggregation
{
    public const TYPE = 'nested';

    public function __construct(
        public string $path,
    ) {
        # code...
    }

    public function getPayload(): array
    {
        return [
            'path' => $this->path,
        ];
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }
}
