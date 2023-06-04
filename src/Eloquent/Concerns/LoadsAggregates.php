<?php

namespace Elastico\Eloquent\Concerns;

use Closure;
use Illuminate\Support\Str;
use Elastico\Query\MatchAll;
use InvalidArgumentException;
use Elastico\Eloquent\Builder;
use Illuminate\Support\Collection;
use Elastico\Aggregations\Metric\Avg;
use Elastico\Aggregations\Metric\Max;
use Elastico\Aggregations\Metric\Min;
use Elastico\Aggregations\Metric\Sum;
use Elastico\Query\Response\Response;
use Elastico\Aggregations\Aggregation;
use Elastico\Relations\ElasticRelation;
use Illuminate\Database\Eloquent\Model;
use Elastico\Aggregations\Bucket\Filter;
use Elastico\Aggregations\Metric\Cardinality;

use Elastico\Eloquent\Concerns\ParsesRelationships;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

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
                    'count' => new Filter(filter: MatchAll::make('all')),
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

    public function withAggregation(string|array $relations, iterable $aggregations): static
    {
        $this->withAggregations[]  = func_get_args();

        return $this;
    }

    public function withAggregations(array $aggregations): static
    {
        foreach ($aggregations as [$relations, $aggregation]) {
            $this->withAggregation(relations: $relations, aggregations: $aggregation);
        }

        return $this;
    }

    public function eagerLoadAggregations(array $models): array
    {
        $aggregations = $this->withAggregations;

        if (empty($aggregations)) {
            return $models;
        }


        $items = collect($models)
            ->keyBy(fn (Model $item) => $item->getKey());

        return $items
            ->getBulk(function (Model $item) use ($aggregations): Collection {
                return collect($aggregations)
                    ->map(function (array $aggregation, string $aggregation_key) use ($item): Collection {
                        [$relations, $aggregation] = $aggregation;

                        $relations = $item
                            ->newQueryWithoutRelationships()
                            ->parseRelationArray(!is_array($relations) ? [$relations] : $relations);

                        return collect($relations)
                            ->keyBy(fn ($relation, string $relation_key): string => Str::after($relation_key, ' as '))
                            ->keyBy(fn ($relation, string $relation_key): string  => implode(static::AGGREGATION_SEPARATOR, [$relation_key, $aggregation_key]))
                            ->map(fn (Closure $relation): Builder|Relation  => $relation($item))
                            ->each(function (Relation|Builder $relation) use ($aggregation, $item): void {
                                collect(is_iterable($aggregation) ? $aggregation : [$aggregation])
                                    ->each(function (Aggregation|Closure $aggregation, string $name)   use ($item, $relation): void {
                                        if ($aggregation instanceof Aggregation) {
                                            $relation->take(0)->addAggregation($name, $aggregation);
                                        } else if ($aggregation instanceof Closure) {
                                            $aggregation($relation, $item);
                                        } else {
                                            throw new InvalidArgumentException('Invalid aggregation: ' . $aggregation);
                                        }
                                    });
                            });
                    })
                    ->collapse();
                # code...
            })
            // ->map(function (Model $item, string $model_key) use ($aggregations): Collection {
            // })
            // ->collapse()
            // ->pipe(fn (Collection $queries): Collection  => $queries->isEmpty()
            //     ? collect()
            //     // TODO:  group relations by connection to load from multiple clusters?
            //     : collect($queries->first()->getConnection()->query()->getMany($queries)))
            // ->groupBy(
            //     groupBy: fn (Response $response, string $key): string => explode(static::AGGREGATION_SEPARATOR, $key, 2)[0],
            //     preserveKeys: true
            // )
            // ->map(fn (Collection $group, string $item_id): Collection => $group->keyBy(fn ($r, string $key) => explode(static::AGGREGATION_SEPARATOR, $key, 2)[1] . static::AGGREGATION_SEPARATOR . explode(static::AGGREGATION_SEPARATOR, $key, 3)[2]))
            ->map(fn (Collection $group, string $item_id): Collection => $group
                ->each(fn (Response $response, string $response_key) => $items->get($item_id)->addAggregations(explode(static::AGGREGATION_SEPARATOR, $response_key, 2)[0], $response->aggregations())))
            ->pipe(fn (Collection $c): Collection => $items)
            ->all();
    }

    public function resolveAggregates(array $models): array
    {
        $aggregates = $this->withAggregates;
        if (empty($aggregates)) {
            return $models;
        }

        return collect($models)
            ->map(function (Model $model) use ($aggregates) {
                $extraAttributes = collect($aggregates)
                    ->flatMap(function (array $aggregate) use ($model) {
                        [$relations, $column, $function] = $aggregate;
                        $relations = !is_array($relations) ? [$relations] : $relations;
                        $relations = $this->parseRelationArray($relations);

                        return collect($relations)
                            ->keyBy(fn (Closure $relation, string $relation_key) => Str::after($relation_key, ' as '))
                            ->mapWithKeys(function (Closure $rel, string $relation) use ($model, $function, $column): array {
                                if ($column === '*' || $column === ['*']) {
                                    $column = null;
                                }
                                $field_name = implode('_', array_filter([$relation, $function, $column]));
                                $field_name = $relation;


                                $value = match ($function) {
                                    'count' => $model->getAggregations($relation)->get($function)->doc_count(),
                                    'cardinality',
                                    'sum',
                                    'avg',
                                    'max',
                                    'min' => $model->getAggregations($relation)->get($function)->value(),
                                    default => throw new InvalidArgumentException('Invalid aggregate function: ' . $function),
                                };
                                return [$field_name => $value];
                            });
                    })
                    ->all();

                return $model->forceFill($extraAttributes)
                    ->syncOriginalAttributes(array_keys($extraAttributes))
                    // ->mergeCasts($models->get($model->getKey())->getCasts())
                ;
            })
            ->all();
    }


    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseRelationArray(array $relations)
    {
        if (empty($relations)) {
            return [];
        }

        return collect($relations)
            // ->pipe(fn(Collection $relations) => )
            ->mapWithKeys(function (string|array|Closure $relation, string $key) {

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

                return [$name => function (Model $model) use ($relation, $constraints): EloquentBuilder|Relation {
                    return is_string($relation)
                        ? $constraints($model->{$relation}(), $model)
                        : $constraints($relation[1]($model->{$relation}()), $model);
                }];
            })
            ->all();
    }
}
