<?php

namespace Elastico\Eloquent\Relations\Concerns;

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

    public function matchesAttributes(Model $model, Model $related): bool
    {
        $matches = $this->getAttributeMatches();

        if ($matches->isEmpty()) {
            return true;
        }

        return $matches->every(static function (array $match) use ($model, $related) {
            $localKey = Arr::get($model, $match['localKey']);
            $foreignKey = Arr::get($related, $match['foreignKey']);

            if (empty($localKey) || empty($foreignKey)) {
                return false;
            }

            if (is_array($localKey)) {
                return in_array($foreignKey, $localKey);
            }

            return $localKey === $foreignKey;
        });
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
