<?php

namespace Elastico\Aggregations\Bucket;

use Exception;
use stdClass;

/**
 * SignificantTerms Aggregation.
 */
class SignificantTerms extends BucketAggregation
{
    public const TYPE = 'significant_terms';


    public function __construct(
        public string $field,
        public string $significance_type,
        public null|array $background_filter = null,
        public null|int $min_doc_count = null,
        public int $size = 10,
        public $include = null,
        public $exclude = null,
        public null|string $execution_hint = null,
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
        if (!is_null($this->execution_hint)) {
            $agg['execution_hint'] = $this->execution_hint;
        }

        return $agg;
    }

    public function type(string $type): self
    {
        if (!in_array($type, ['jlh', 'mutual_information', 'chi_square', 'gnd', 'percentage'])) {
            throw new Exception('Invalid Significance Type');
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

    public function execution_hint(string $execution_hint): self
    {
        $this->execution_hint = $execution_hint;

        return $this;
    }
}
