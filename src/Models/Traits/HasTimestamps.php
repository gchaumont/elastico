<?php

namespace Elastico\Models\Traits;

use DateTime;
use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;

trait HasTimestamps
{
    #[Field(type: FieldType::date)]
    public null|DateTime $created_at;

    #[Field(type: FieldType::date)]
    public null|DateTime $updated_at;

    public function updated(DateTime $timestamp): static
    {
        $this->updated_at = $timestamp;

        return $this;
    }
}
