<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * ReverseNested Aggregation.
 */
class ReverseNested extends BucketAggregation
{
    public const TYPE = 'reverse_nested';

    public function __construct(
        public ?string $path = null,
    ) {
    }

    public function getPayload(): array
    {
        return $this->path ? [
            'path' => $this->path,
        ] : [];
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }
}
