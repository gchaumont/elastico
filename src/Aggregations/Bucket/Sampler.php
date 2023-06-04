<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Sampler Aggregation.
 */
class Sampler extends BucketAggregation
{
    public const TYPE = 'sampler';

    public function __construct(
        public ?int $shard_size = null,
    ) {
    }

    public function getPayload(): array
    {
        return array_filter([
            'shard_size' => $this->shard_size,
        ]);
    }

    public function shardSize(int $shardSize): self
    {
        $this->shard_size = $shardSize;

        return $this;
    }
}
