<?php

namespace Elastico\Models\Traits;

use DateTime;
use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;

trait SoftDeletes
{
    #[Field(type: FieldType::date)]
    public null|DateTime $deleted_at;

    #[Field(type: FieldType::boolean)]
    public bool $deleted;

    public function delete(null|bool|string $refresh = null): void
    {
        $this->deleted();

        $this->save($refresh);
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
