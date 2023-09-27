<?php

namespace Elastico\Query\Builder;


trait RequestsColumns
{
    public array $requested_columns = [];

    public function getRequestedColumns(): array
    {
        if (!empty($this->requested_columns)) {
            return $this->requested_columns;
        }
        if (!empty($this->columns) && $this->columns !== ['*']) {
            return $this->columns;
        }

        return [];
    }

    public function setRequestedColumns(array $columns): self
    {
        $this->requested_columns = $columns;

        return $this;
    }
}
