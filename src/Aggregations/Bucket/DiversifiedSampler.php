<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;
use Elastico\Aggregations\Options\ExecutionHint;
use Exception;

/**
 * DiversifiedSampler Aggregation.
 */
class DiversifiedSampler extends BucketAggregation
{
    public string $type = 'diversified_sampler';

    public int $shardSize;

    public int $maxDocsPerValue;

    public string $field;

    public function getPayload(): array
    {
        $payload = [
            'field' => $this->field,
        ];

        if (isset($this->shard_size)) {
            $payload['shard_size'] = $this->shard_size;
        }

        if (isset($this->maxDocsPerValue)) {
            $payload['max_docs_per_value'] = $this->maxDocsPerValue;
        }

        return $payload;
    }

    public function maxDocsPerValue(int $max): self
    {
        $this->maxDocsPerValue = $max;

        return $this;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function shardSize(int $shardSize): self
    {
        $this->shardSize = $shardSize;

        return $this;
    }

    public function execution_hint(string|ExecutionHint $execution_hint): self
    {
        if (!in_array($execution_hint, ['map', 'global_ordinals', 'bytes_hash'])) {
            throw new Exception('Unallowed execution_hint value', 1);
        }
        $this->execution_hint = (string) $execution_hint;

        return $this;
    }
}
