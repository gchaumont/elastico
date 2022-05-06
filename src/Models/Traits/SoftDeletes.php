<?php

namespace Gchaumont\Models\Traits;

use App\Support\Supervision\Dashboard\Fields\AdminField;
use DateTime;
use Gchaumont\Mapping\Field;
use Gchaumont\Mapping\FieldType;

trait SoftDeletes
{
    #[Field(type: FieldType::date)]
    #[AdminField(name: 'Deleted', truncate: 25, sortable: true)]
    public null|DateTime $deleted_at;

    #[Field(type: FieldType::boolean)]
    public bool $deleted;

    public function delete(): void
    {
        $this->deleted();

        $this->save();
    }

    public function deleted(DateTime $timestamp = new DateTime()): static
    {
        $this->deleted_at = $timestamp;
        $this->deleted = true;

        return $this;
    }

    public function restored(): static
    {
        $this->deleted_at = null;
        $this->deleted = false;

        return $this;
    }
}
