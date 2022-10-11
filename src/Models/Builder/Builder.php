<?php

namespace Elastico\Models\Builder;

use Closure;
use Elastico\Models\Model;
use Elastico\Models\Relations\Relation;
use Elastico\Query\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    protected array $with = [];

    public function __construct(
        null|string $connection = null,
        public readonly string $model,
    ) {
        parent::__construct($connection);

        $this->index($this->model::searchableIndexName());
    }

    public function getModel(): Model
    {
        return new $this->model();
    }

    public function scoped(string $scope, mixed $params = null): self
    {
        return $this->model::scoped($scope, $this, $params);
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function with(string|array $relations /*, Closure $callback = null*/): self
    {
        $this->with = is_string($relations) ? func_get_args() : $relations;

        return $this;
    }

    public function eagerLoadRelations(iterable $models): iterable
    {
        foreach ($this->with as $relation) {
            if (!str_contains($relation, '.')) {
                $models = $this->eagerLoadRelation(
                    models: collect($models)->all(),
                    name: $relation,
                    // constraints: $constraints
                );
            }
        }

        return $models;
    }

    public function getRelation(string $relation): Relation
    {
        return $this->getModel()->getRelation(relation: $relation);
    }

    public function eagerLoadRelation(array $models, string $name /*, Closure $constraints*/)
    {
        $relation = $this->getRelation(relation: $name);

        $relation->addEagerConstraints($models);

        // $constraints($relation);

        return $relation->match(
            models: $models,
            results: $relation->getResults(),
            relation: $name,
        );
    }

    public function withAllRelations(): self
    {
        $this->with = ['*'];

        return $this;
    }

    protected function parseWithRelations(array $relations): array
    {
        if ([] === $relations) {
            return [];
        }
        $results = [];

        foreach ($relations as $relation) {
            $results[$relation] ??= static function () {
            };
        }

        return $results;
    }

    // protected function loadRelations(Collection $hits): Collection
    // {
    //     if (empty($hits) || empty($this->with)) {
    //         return [];
    //     }
    //     if (['*'] === $this->with) {
    //         $this->with = $hits[0]::getAllRelations();
    //     }

    //     // GROUPED RELATION LOADING
    //     $docs = collect();
    //     foreach ($this->with as $relation) {
    //         $propName = $hits[0]::getPropertyNameForClass($relation);

    //         $docs = $docs->concat(
    //             collect($hits)
    //                 ->filter(fn ($hit) => isset($hit->{$propName}))
    //                 ->map(fn ($hit) => $hit->{$propName}->getKey())
    //                 ->filter()
    //                 ->unique()
    //                 ->map(fn ($id) => [
    //                     '_index' => $relation::searchableIndexName(),
    //                     '_id' => $id,
    //                     '_source' => '*',
    //                 ])
    //         )
    //             ;
    //     }

    //     if (!empty($payload)) {
    //         $query->startingQuery(
    //             endpoint: 'mget',
    //         );

    //         $query->response = $response = static::getClient()->mget(['body' => ['docs' => $docs->all()]]);

    //         $response['docs'] = array_filter($response['docs'], fn ($d) => true == ($d['found'] ?? false));

    //         $relatedModels = $query->unserialiseHits($response['docs'], count($response['docs']));

    //         $query->endingQuery(
    //             endpoint: 'mget',
    //             operation: 'get',
    //             queries: count($payload['body']['docs']),
    //             docs: count($payload['body']['docs']),
    //             ids: array_column($payload['body']['docs'], '_id')
    //         );

    //         foreach ($this->with as $relatedClass) {
    //             $propName = $hits[0]::getPropertyNameForClass($relatedClass);
    //             $hits = collect($hits);
    //             $hits = $hits->transform(function ($hit) use ($propName, $relatedModels, $relatedClass) {
    //                 if ($id = (isset($hit->{$propName}) ? $hit->{$propName}->getKey() : null)) {
    //                     $relatedClassModels = array_filter($relatedModels, fn ($model) => $model instanceof $relatedClass && $model->getKey() == $id);
    //                     // response($relatedClassModels)->send();
    //                     if (!empty($relatedClassModels)) {
    //                         $hit->{$propName} = reset($relatedClassModels); // TODO : handle model not found better
    //                     }
    //                 }

    //                 return $hit;
    //             });
    //         }
    //     }

    //     return collect($hits)->all();
    // }
}
