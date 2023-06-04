<?php

namespace Elastico\Aggregations\Pipeline;

use Elastico\Aggregations\Aggregation;

/**
 * Avg Aggregation.
 */
class AvgBucket extends Aggregation
{
    public const TYPE = 'avg_bucket';

    public function __construct(
        public string $buckets_path,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'buckets_path' => $this->buckets_path,
        ];
    }

    public function path(string $buckets_path): self
    {
        $this->buckets_path = $buckets_path;

        return $this;
    }
}
