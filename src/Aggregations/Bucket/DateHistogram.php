<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Terms Aggregation.
 */
class DateHistogram extends BucketAggregation
{
    public const TYPE = 'date_histogram';

    public function __construct(
        public string $field,
        public null|string $calendar_interval = null,
        public null|string $fixed_interval = null,
        public null|string $format = null,
        public null|string $time_zone = null,
        public null|int $min_doc_count = null,
        public null|array $extended_bounds = null,
    ) {
    }

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
        ];
        if (!is_null($this->calendar_interval)) {
            $agg['calendar_interval'] = $this->calendar_interval;
        }
        if (!is_null($this->fixed_interval)) {
            $agg['fixed_interval'] = $this->fixed_interval;
        }

        if (!is_null($this->format)) {
            $agg['format'] = $this->format;
        }
        if (!is_null($this->time_zone)) {
            $agg['time_zone'] = $this->time_zone;
        }
        if (!is_null($this->min_doc_count)) {
            $agg['min_doc_count'] = $this->min_doc_count;
        }

        if (!is_null($this->extended_bounds)) {
            $agg['extended_bounds'] = $this->extended_bounds;
        }

        return $agg;
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function min(int $min_doc_count): self
    {
        $this->min_doc_count = $min_doc_count;

        return $this;
    }

    public function extended_bounds(array $extended_bounds): self
    {
        $this->extended_bounds = $extended_bounds;

        return $this;
    }

    public function calendarInterval(string $calendar_interval): self
    {
        $this->calendar_interval = $calendar_interval;

        return $this;
    }

    public function fixedInterval(string $fixed_interval): self
    {
        $this->fixed_interval = $fixed_interval;

        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function timezone(string $time_zone): self
    {
        $this->time_zone = $time_zone;

        return $this;
    }
}
