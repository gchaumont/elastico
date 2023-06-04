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
    public const TYPE = 'diversified_sampler';

    public function __construct(
        public string $field,
        public null|int $shard_size = null,
        public null|int $max_docs_per_value = null,
        public null|string $execution_hint = null,
    ) {
    }

    public function getPayload(): array
    {
        $payload = [
            'field' => $this->field,
        ];

        if (!is_null($this->shard_size)) {
            $payload['shard_size'] = $this->shard_size;
        }

        if (!is_null($this->max_docs_per_value)) {
            $payload['max_docs_per_value'] = $this->max_docs_per_value;
        }

        if (!is_null($this->execution_hint)) {
            $payload['execution_hint'] = $this->execution_hint;
        }

        return $payload;
    }

    public function maxDocsPerValue(int $max): self
    {
        $this->max_docs_per_value = $max;

        return $this;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function shardSize(int $shardSize): self
    {
        $this->shard_size = $shardSize;

        return $this;
    }

    public function execution_hint(string $execution_hint): self
    {
        if (!in_array($execution_hint, ['map', 'global_ordinals', 'bytes_hash'])) {
            throw new Exception('Unallowed execution_hint value', 1);
        }
        $this->execution_hint = (string) $execution_hint;

        return $this;
    }
}
