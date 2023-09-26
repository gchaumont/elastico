<?php

namespace Elastico\Eloquent\Concerns;

use Elastico\Query\Query;
use Illuminate\Support\Arr;
use Elastico\Eloquent\Model;
use Elastico\Query\MatchNone;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\Response\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Allows to add additional constraints the Relation Queries
 */
trait MatchesAttributes
{
    protected array $attribute_matches = [];

    public function whereMatches(string $localKey, string $foreignKey = null): static
    {
        $foreignKey ??= $localKey;

        $this->attribute_matches[] = [
            'localKey' => $localKey,
            'foreignKey' => $foreignKey,
        ];

        return $this;
    }

    public function hasAttributeMatches(): bool
    {
        return !empty($this->attribute_matches);
    }

    public function getAttributeMatches(): BaseCollection
    {
        return BaseCollection::make($this->attribute_matches);
    }

    public function getAttributeMatchesQuery(Model $model): Boolean
    {
        $query = new Boolean();

        $this->getAttributeMatches()->each(static function (array $match) use ($model, $query) {
            $keys = Arr::get($model, $match['foreignKey']);
            if (empty($keys)) {
                $query->filter(new MatchNone());
                return;
            }

            $query->where($match['localKey'], '=', $keys);
        });


        return $query;
    }
}
