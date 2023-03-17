<?php

namespace Elastico\Query\Builder;

trait HasKnn
{
    public null|array $knn = null;

    public function knn(string $field, array $vector, int $k, int $candidates, array $filter = []): static
    {
        $this->knn = array_filter([
            'field' => $field,
            'query_vector' => $vector,
            'k' => $k,
            'num_candidates' => $candidates,
            'filter' => $filter,
        ]);

        return $this;
    }
}
