<?php

namespace Elastico\Aggregations\Bucket;

use Elastico\Aggregations\Aggregation;

/**
 * Terms Aggregation.
 */
class DateHistogram extends BucketAggregation
{
    public string $type = 'date_histogram';

    public string $field;

    public string $calendar_interval;

    public string $fixed_interval;

    public string $format;

    public string $time_zone;

    public string $min_doc_count;

    public array $extended_bounds;

    public function getPayload(): array
    {
        $agg = [
            'field' => $this->field,
        ];
        if (!empty($this->calendar_interval)) {
            $agg['calendar_interval'] = $this->calendar_interval;
        }
        if (!empty($this->fixed_interval)) {
            $agg['fixed_interval'] = $this->fixed_interval;
        }

        if (!empty($this->format)) {
            $agg['format'] = $this->format;
        }
        if (!empty($this->time_zone)) {
            $agg['time_zone'] = $this->time_zone;
        }
        if (isset($this->min_doc_count)) {
            $agg['min_doc_count'] = $this->min_doc_count;
        }

        if (isset($this->extended_bounds)) {
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
