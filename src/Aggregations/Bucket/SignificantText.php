<?php

namespace Elastico\Aggregations\Bucket;

use stdClass;
use Exception;
use Elastico\Aggregations\Aggregation;

/**
 * SignificantText Aggregation.
 */
class SignificantText extends BucketAggregation
{
    public const TYPE = 'significant_text';

    public function __construct(
        public string $field,
        public string $significance_type,
        public null|bool $filter_duplicate_text = null,
        public null|array $background_filter = null,
        public null|int $min_doc_count = null,
        public int $size = 10,
        public $include = null,
        public $exclude = null,
    ) {
    }

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
            $this->significance_type => new stdClass(),
            'size' => $this->size,
        ];
        if (!is_null($this->include)) {
            $agg['include'] = $this->include;
        }
        if (!is_null($this->filter_duplicate_text)) {
            $agg['filter_duplicate_text'] = $this->filter_duplicate_text;
        }
        if (!is_null($this->exclude)) {
            $agg['exclude'] = $this->exclude;
        }
        if (!empty($this->min_doc_count)) {
            $agg['min_doc_count'] = $this->min_doc_count;
        }
        if (!empty($this->background_filter)) {
            $agg['background_filter'] = $this->background_filter;
            $agg[$this->significance_type] = ['background_is_superset' => false];
        }

        return $agg;
    }

    public function type(string $type): self
    {
        if (!in_array($type, ['jlh', 'mutual_information', 'chi_square', 'gnd', 'percentage'])) {
            throw new Exception('Invalid Significane Type', 1);
        }

        $this->significance_type = $type;

        return $this;
    }

    public function min(int $doc_count): self
    {
        $this->min_doc_count = $doc_count;

        return $this;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function filterDuplicateText(bool $filter_duplicate_text): self
    {
        $this->filter_duplicate_text = $filter_duplicate_text;

        return $this;
    }

    public function backgroundFilter(array $background_filter): self
    {
        $this->background_filter = $background_filter;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

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
}
