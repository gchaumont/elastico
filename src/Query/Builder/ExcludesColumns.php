<?php

namespace Elastico\Query\Builder;

use Elastico\Eloquent\Model;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\Query;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
