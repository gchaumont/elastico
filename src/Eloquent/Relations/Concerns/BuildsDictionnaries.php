<?php

namespace Elastico\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Collection;

trait BuildsDictionnaries
{

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->getForeignKeyName();


        $dictionary = [];

        foreach ($results as $result) {
            $value = $this->getDictionaryKey($result->getAttribute($foreign));

            if (is_iterable($value)) {
                foreach ($value as $v) {
                    $dictionary[$v][] = $result;
                }
            } else {
                $dictionary[$value][] = $result;
            }
        }

        return $dictionary;

        return $results->mapToDictionary(function ($result) use ($foreign) {
            return [$this->getDictionaryKey($result->getAttribute($foreign)) => $result];
        })->all();
    }
}
