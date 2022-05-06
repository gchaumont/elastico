<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Terms Aggregation.
 */
class Terms extends BucketAggregation
{
    public string $type = 'terms';

    public string $field;

    public int $minDocCount;

    public int $size = 10;

    public string $missing;

    public $include;

    public $exclude;

    public string $execution_hint;

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
            'size' => $this->size,
        ];
        if (isset($this->include)) {
            $agg['include'] = $this->include;
        }
        if (isset($this->exclude)) {
            $agg['exclude'] = $this->exclude;
        }
        if (isset($this->minDocCount)) {
            $agg['min_doc_count'] = $this->minDocCount;
        }
        if (isset($this->missing)) {
            $agg['missing'] = $this->missing;
        }
        if (isset($this->execution_hint)) {
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
        $this->minDocCount = $min;

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
