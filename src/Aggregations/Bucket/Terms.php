<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Terms Aggregation.
 */
class Terms extends BucketAggregation
{
    public const TYPE = 'terms';

    public function __construct(
        public string $field,
        public int $size = 10,
        public null|int $min_doc_count = null,
        public null|string $missing = null,
        public null|string $execution_hint = null,
        public $include = null,
        public  $exclude = null,
    ) {
        # code...
    }

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
            'size' => $this->size,
        ];
        if (!is_null($this->include)) {
            $agg['include'] = $this->include;
        }
        if (!is_null($this->exclude)) {
            $agg['exclude'] = $this->exclude;
        }
        if (!is_null($this->min_doc_count)) {
            $agg['min_doc_count'] = $this->min_doc_count;
        }
        if (!is_null($this->missing)) {
            $agg['missing'] = $this->missing;
        }
        if (!is_null($this->execution_hint)) {
            $agg['execution_hint'] = $this->execution_hint;
        }

        return $agg;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function min(int $min): self
    {
        $this->min_doc_count = $min;

        return $this;
    }

    public function missing(string $value): self
    {
        $this->missing = $value;

        return $this;
    }

    public function exclude($exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    public function include($include): self
    {
        $this->include = $include;

        return $this;
    }

    public function execution_hint(string $execution_hint): self
    {
        $this->execution_hint = $execution_hint;

        return $this;
    }
}
