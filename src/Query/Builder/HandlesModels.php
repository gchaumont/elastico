<?php

namespace Elastico\Query\Builder;

trait HandlesModels
{
    public function scoped(string $scope, mixed $params = null): self
    {
        return $this->searchableModel::scoped($scope, $this, $params);
    }
}
