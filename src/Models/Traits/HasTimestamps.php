<?php

namespace Gchaumont\Models\Traits;

use App\Support\Supervision\Dashboard\Fields\AdminField;
use DateTime;
use Gchaumont\Mapping\Field;
use Gchaumont\Mapping\FieldType;

trait HasTimestamps
{
    #[Field(type: FieldType::date)]
    #[AdminField(sortable: true)]
    public null|DateTime $created_at;

    #[Field(type: FieldType::date)]
    #[AdminField(name: 'Updated', sortable: true)]
    public null|DateTime $updated_at;

    public function updated(DateTime $timestamp): static
    {
        $this->updated_at = $timestamp;

        return $this;
    }
}
