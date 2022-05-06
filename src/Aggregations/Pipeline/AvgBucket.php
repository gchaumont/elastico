<?php

namespace Gchaumont\Aggregations\Pipeline;

use Gchaumont\Aggregations\Aggregation;

/**
 * Avg Aggregation.
 */
class AvgBucket extends Aggregation
{
    public string $type = 'avg_bucket';

    public string $buckets_path;

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
