<?php

namespace Gchaumont\Aggregations\Bucket;

use Gchaumont\Aggregations\Aggregation;

/**
 * Sampler Aggregation.
 */
class Sampler extends BucketAggregation
{
    public string $type = 'sampler';

    public int $shardSize;

    public function getPayload(): array
    {
        return array_filter([
            'shard_size' => $this->shardSize ?? null,
        ]);
    }

    public function shardSize(int $shardSize): self
    {
        $this->shardSize = $shardSize;

        return $this;
    }
}
