<?php

namespace Elastico\Query\Builder;

use Illuminate\Support\Arr;

trait ExcludesColumns
{
    public array $exclude_columns;

    public function exclude(string|array ...$columns): static
    {
        $columns = Arr::flatten($columns);

        $this->exclude_columns = array_merge($this->exclude_columns ?? [], $columns);

        return $this;
    }
}
