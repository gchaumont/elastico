<?php

namespace Elastico\Eloquent\Concerns;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Elastico\Query\MatchAll;
use Elastico\Query\Term\Term;
use InvalidArgumentException;
use Elastico\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Elastico\Aggregations\Metric\Avg;
use Elastico\Aggregations\Metric\Max;
use Elastico\Aggregations\Metric\Min;
use Elastico\Aggregations\Metric\Sum;
use Elastico\Aggregations\Aggregation;
use Elastico\Query\Response\Collection;
use Elastico\Aggregations\Bucket\Filter;
use Elastico\Eloquent\Relations\HasMany;
use Elastico\Aggregations\Metric\Cardinality;
use Elastico\Eloquent\Relations\BelongsToMany;
use Elastico\Eloquent\Relations\ElasticRelation;
use Elastico\Eloquent\Concerns\ParsesRelationships;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Eloquent\Model;
use Elastico\Query\Response\Aggregation\AggregationResponse;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * @mixin Builder
 */
trait LoadsAggregates
{
    use ParsesRelationships;

    const  AGGREGATION_SEPARATOR = '::';

    // laravel aggregates
    protected array $withAggregates = [];

    // full elasticsearch aggregations
    protected array $withAggregations = [];


    /**
     * Add subselect queries to include an aggregate value for a relationship.
     *
     * @param  mixed  $relations
     * @param  string  $column
     * @param  string  $function
     * @return $this
     */
    public function withAggregate($relations, $column, $function = null)
    {
        if (empty($relations)) {
            return $this;
        };

        $relations = $this->partitionEloquentElasticRelationships($this->getModel(), !is_array($relations) ? [$relations] : $relations);

        if ($relations['elastic']) {

            $this->withAggregates[] = [$relations['elastic'], $column, $function];

            $this->withAggregation(
                $relations['elastic'],
                [$function => match ($function) {
                    "exists",
                    'count' => new Filter(filter: new MatchAll),
                    'sum' => new Sum(field: $column),
                    'avg' => new Avg(field: $column),
                    'max' => new Max(field: $column),
                    'min' => new Min(field: $column),
                    'cardinality' => new Cardinality(field: $column),
                    default => throw new InvalidArgumentException('Invalid aggregate function: ' . $function),
                }]
            );
        }

        if ($relations['eloquent']) {
            return parent::withAggregate($relations['eloquent'], $column, $function);
        }

        return $this;
    }

    public function withAggregation(string|array $relations, iterable $aggregations, bool $separate_queries = false): static
    {
        $this->withAggregations[]  = [
            'relations' => $relations,
            'aggregations' => $aggregations,
            'separate_queries' => $separate_queries
        ];

        return $this;
    }

    public function withAggregations(array $aggregations, bool $separate_queries = false): static
    {
        foreach ($aggregations as [$relations, $aggregation]) {
            // TODO: check if the aggregation itself has a separate_queries property
            $this->withAggregation(
                relations: $relations,
                aggregations: $aggregation,
                separate_queries: $separate_queries
            );
        }

        return $this;
    }

    public function eagerLoadAggregations(array $models): array
    {
        $aggregations = collect($this->withAggregations);

        if ($aggregations->isEmpty() || empty($models)) {
            return $models;
        }


        $models = $this->loadAggregationsGrouped(
            models: $models,
            aggregations: $aggregations->filter(static fn (array $aggregation): bool => $aggregation['separate_queries'] === false)->all()
        );

        $models = $this->loadAggregationsSeparate(
            models: $models,
            aggregations: $aggregations->filter(static fn (array $aggregation): bool => $aggregation['separate_queries'] === true)->all()
        );

        return $models;
    }

    protected function loadAggregationsGrouped(array $models, array $aggregations): array
    {
        if (empty($aggregations)) {
            return $models;
        }
        $items = collect($models);

        $aggregations = collect($aggregations)
            ->map(function (array $aggregation, $index) use ($models): array {
                $aggregation['relations'] = is_array($aggregation['relations']) ? $aggregation['relations'] : [$aggregation['relations']];

                $aggregation['eager_loads'] = collect($this->parseWithRelations($aggregation['relations']))
                    ->map(function ($constraints, $name) use ($models, $aggregation): null|Relation {
                        // First we will determine if the name has been aliased using an "as" clause on the name
                        // and if it has we will extract the actual relationship name and the desired name of
                        // the resulting column. This allows multiple aggregates on the same relationships.
                        $segments = explode(' ', $name);

                        unset($alias);

                        if (count($segments) === 3 && Str::lower($segments[1]) === 'as') {
                            [$name, $alias] = [$segments[0], $segments[2]];
                        }

                        $relation = $this->getRelationWithoutConstraints($name);

                        $relation = $constraints($relation);

                        $relation->addEagerConstraints($models);

                        foreach ($models as $model) {
                            $relation->addAggregation('model:' . $model->getKey(), (new Filter(filter: $relation->buildConstraint($model)))
                                ->addAggregations(static::makeAggregationsForModel($aggregation['aggregations'], $model)));
                        }


                        return $relation;
                    })
                    ->filter()
                    ->keyBy(static fn (Relation $relation, $name): string => $index . '::' . $name);

                // $aggregation['results'] = $aggregation['eager_loads']->map(static fn (Relation $relation) => match (true) {
                //     $relation instanceof HasMany => $relation->getEager(),
                //     $relation instanceof BelongsToMany => $relation->getResults(),
                //     default => throw new InvalidArgumentException('Invalid relation type: ' . get_class($relation))
                // });

                return $aggregation;
            });

        $relation_hash = fn (string $relation, Relation $query) => Str::before(Str::after($relation, '::'), ' as ') . '::' .
            hash(algo: 'murmur3a', data: json_encode($query->getQuery()->getQuery()->wheres));

        $results = $aggregations
            ->flatMap(static fn (array $aggregation): BaseCollection => $aggregation['eager_loads'])
            ->groupBy(static fn (Relation $relation, $key) => $relation_hash($key, $relation), preserveKeys: true)
            ->map(static fn (BaseCollection $relations): Relation => $relations
                ->reduce(static fn (null|Relation $carry, Relation $relation): Relation => $carry ? $carry->mergeAggregations($relation) : $relation))
            // ->each(static fn (Relation $relation) => dump($relation->getBaseQuery()->getConnection()))
            ->groupBy(static fn (Relation $relation): string => $relation->getBaseQuery()->getConnection()->getName(), preserveKeys: true)
            // ->each(static fn (BaseCollection $queries, string $connection) => dd($queries->first()->toSql()))
            ->map(static fn (BaseCollection $queries, string $connection): array => DB::connection($connection)->query()->getMany($queries->all()))
            ->collapse();



        return $items->each(function (Model $item) use ($results, $aggregations, $relation_hash): void {
            $aggregations->each(function (array $aggregation) use ($item, $results, $relation_hash): void {
                $aggregation['eager_loads']->each(function (Relation $relation, string $relation_name) use ($item, $results, $relation_hash): void {
                    $actual_relation_name = Str::after(Str::after($relation_name, '::'), ' as ');

                    $aggregations = $results->get($relation_hash($relation_name, $relation))->aggregation('model:' . $item->getKey())->aggregations();

                    $aggregations = $aggregations->put('_total', $results->get($relation_hash($relation_name, $relation))->aggregation('model:' . $item->getKey())->doc_count());

                    $item->addAggregations(
                        relation: $actual_relation_name,
                        aggregations: $aggregations,
                    );
                });
            });
        })
            ->tap(fn (BaseCollection $items): array => $this->resolveAggregates($items->all()))
            ->all();
    }

    protected function loadAggregationsSeparate(array $models, array $aggregations): array
    {
        if (empty($aggregations)) {
            return $models;
        }

        $items = collect($models);

        $responses = $items
            ->getBulk(static function (Model $item) use ($aggregations): BaseCollection {
                return collect($aggregations)
                    ->map(static function (array $aggregations, string $aggregation_key) use ($item): BaseCollection {
                        ['relations' => $relations, 'aggregations' => $aggregations] = $aggregations;

                        return $item
                            ->newQueryWithoutRelationships()
                            ->parseRelationArray(!is_array($relations) ? [$relations] : $relations)
                            ->keyBy(static fn ($relation, string $relation_key): string => Str::after($relation_key, ' as '))
                            ->keyBy(static fn ($relation, string $relation_key): string  => implode(static::AGGREGATION_SEPARATOR, [$relation_key, $aggregation_key]))
                            ->map(static fn (Closure $relation): Builder|Relation  => $relation($item))
                            ->each(static function (Relation|Builder $relation) use ($aggregations, $item): void {
                                $relation->take(0)->addAggregations(static::makeAggregationsForModel($aggregations, $item));
                            });
                    })
                    ->collapse();
            });

        return $items
            ->each(static fn (Model $item, int $index) => $responses
                ->get($index)
                ?->each(static fn (Collection $response, string $response_key) => $item
                    ->addAggregations(
                        Str::before($response_key, static::AGGREGATION_SEPARATOR),
                        $response->aggregations()
                            ->put('_total', $response->total()),
                    )))
            ->tap(fn (BaseCollection $items): array => $this->resolveAggregates($items->all()))
            ->all();
    }

    protected function resolveAggregates(array $models): array
    {
        $aggregates = $this->withAggregates;

        if (empty($aggregates)) {
            return $models;
        }

        return collect($models)
            ->map(function (Model $model) use ($aggregates): Model {
                $extraAttributes = collect($aggregates)
                    ->flatMap(function (array $aggregate) use ($model): array {
                        [$relations, $column, $function] = $aggregate;

                        $relations = !is_array($relations) ? [$relations] : $relations;


                        return $this
                            ->parseRelationArray($relations)
                            ->mapWithKeys(static function (Closure $rel, string $relation_key) use ($model, $function, $column): array {
                                if ($column === '*' || $column === ['*']) {
                                    $column = null;
                                }
                                if (str_contains($relation_key, ' as ')) {
                                    $field_name = $relation = Str::after($relation_key, ' as ');
                                } else {
                                    $relation = $relation_key;
                                    $field_name = implode('_', array_filter([$relation_key, $function, $column]));
                                }
                                // dump($field_name);
                                // $field_name = $relation;

                                $value = match ($function) {
                                    'count' => $model->getAggregations($relation)->get($function)->doc_count(),
                                    "exists" => $model->getAggregations($relation)->get($function)->doc_count() > 0,
                                    'cardinality',
                                    'sum',
                                    'avg',
                                    'max',
                                    'min' => $model->getAggregations($relation)->get($function)->value(),
                                    default => throw new InvalidArgumentException('Invalid aggregate function: ' . $function),
                                };
                                return [$field_name => $value];
                            })
                            ->all();
                    })
                    ->all();

                return $model->forceFill($extraAttributes)
                    ->syncOriginalAttributes(array_keys($extraAttributes))
                    // ->mergeCasts($models->get($model->getKey())->getCasts())
                ;
            })
            ->all();
    }



    protected static function makeAggregationsForModel(array|Closure $aggregations, Model $model): SupportCollection
    {

        if ($aggregations instanceof Closure) {
            $aggregations = $aggregations($model);
        }

        return collect($aggregations)
            ->map(static fn (Aggregation|Closure $aggregation, string $name): Aggregation => $aggregation instanceof Closure ? $aggregation($model) : $aggregation);
    }


    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     */
    protected function parseRelationArray(array $relations): BaseCollection
    {
        if (empty($relations)) {
            return collect();
        }

        return collect($relations)
            ->mapWithKeys(static function (string|array|Closure $relation, string $key) {

                if (is_numeric($key) && is_string($relation)) {
                    $name = $relation;
                    $constraints = fn (Relation $query) => $query;
                } elseif (is_array($relation) && count($relation) === 1) {
                    $name = array_key_first($relation);
                    $constraints = reset($relation);
                } else if (is_string($key) && is_callable($relation)) {
                    $name = $key;
                    $constraints = $relation;
                } else {
                    // dump('ERROR');
                    // DUMP($relation, $key);
                    throw new InvalidArgumentException('Invalid relation: ' . $relation);
                }

                // $string  = is_numeric($key) ? $relation : $key;
                // $string = is_string($relation) ? $relation : $relation[0];
                if (str_contains($name, ' as ')) {
                    $parts = explode(' as ', $name);
                    [$key, $relation] = [$parts[1],  $parts[0]];
                } else {
                    [$key, $relation] =  [$name, $name];
                }

                return [$name => static function (Model $model) use ($relation, $constraints): EloquentBuilder|Relation {
                    return is_string($relation)
                        ? $constraints($model->{$relation}(), $model)
                        : $constraints($relation[1]($model->{$relation}()), $model);
                }];
            });
    }
}
