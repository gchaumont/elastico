<?php

namespace Elastico\Query;

use Enum;
use stdClass;
use Exception;
use BackedEnum;
use Elastico\Eloquent\Builder;
use Elastico\Mapping\RuntimeField;
use Illuminate\Support\Arr;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Terms;
use Elastico\Scripting\Script;
use Elastico\Query\Term\Exists;
use Elastico\Query\Term\Prefix;
use Elastico\Query\Term\Wildcard;
use Elastico\Query\Compound\Boolean;
use Elastico\Query\Compound\FunctionScore;
use Elastico\Query\Specialized\RankFeature;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
/*
 *  Elasticsearch Query Builder
 *  Extension of Larvel Database Query Builder.
 */

class Grammar extends BaseGrammar
{
    public function compileSelect(BaseBuilder $query)
    {
        return $this->buildPayload($query);
    }

    public function compileCount(BaseBuilder $query)
    {
        $compiled = $this->compileSelect($query);
        unset(
            $compiled['body']['sort'],
            $compiled['body']['aggregations'],
            $compiled['body']['aggs'],
            $compiled['body']['select'],
            $compiled['body']['_source'],
            $compiled['body']['size'],
            $compiled['body']['knn'],
            $compiled['body']['from'],
            $compiled['body']['post_filter'],
            $compiled['body']['suggest'],
        );

        return $compiled;
    }

    public function compileDelete(BaseBuilder $query)
    {
        return $this->compileSelect($query);
    }

    public function compileDeleteMany(BaseBuilder $query, iterable $ids)
    {
        /** @var Builder $query */

        return [
            'options' => [
                'ignore_conflicts' => $query->ignore_conflicts,
            ],
            'body' => collect($ids)
                ->flatMap(static fn ($val): array => [
                    [
                        'delete' => [
                            '_id' => $val,
                            '_index' => $query->from,
                        ],
                    ],
                ])
                ->all(),

        ];
    }

    public function buildPayload(BaseBuilder $query): array
    {
        /** @var Builder $query */

        $payload['index'] = $query->from;

        $payload['options'] = [
            'ignore_conflicts' => $query->ignore_conflicts,
        ];

        $payload['body']['from'] = $query->offset ?? null;

        $payload['body']['size'] = $query->limit ?? null;

        foreach ($query->ranks as $rank) {
            $query->where($rank[0], 'rank', $rank[1]);
        }

        $baseBool = $this->compileWhereComponents($query);

        if (!$baseBool->isEmpty()) {
            $payload['body']['query'] = $baseBool->compile();
        }

        $payload['body']['sort'] = $this->compileOrderComponents($query);

        $payload['body']['post_filter'] = $query->post_filter?->compile();

        if ($query->knn) {
            $payload['body']['knn'] = $query->knn;
        }

        if ($query->collapse) {
            $payload['body']['collapse'] = $query->collapse;
        }

        if ($query->suggest) {
            $payload['body']['suggest'] = $this->compileSuggestComponents($query);
        }

        if ($query->getRuntimeFields()) {
            $payload['body']['runtime_mappings'] = $query->getRuntimeFields()->map(static fn (RuntimeField $field) => $field->toArray())->all();
        }

        if (!empty($query->columns)) {
            $payload['body']['_source']['includes'] = $query->columns;
        }

        if (!empty($query->exclude_columns)) {
            $payload['body']['_source']['excludes'] = $query->exclude_columns;
        }

        $payload['body']['aggs'] = $query->getAggregations()
            ->map(fn ($agg) => $agg->compile())
            ->all();

        $payload['body'] = collect($payload['body'])
            ->reject(fn ($part) => null === $part)
            ->reject(fn ($part) => [] === $part)
            ->all();

        // if ($query->collapse) {
        //     $payload['body']['collapse']['field'] = $query->collapse;
        // }

        // if (!empty($query->post_filter)) {
        //     $payload['body']['post_filter'] = $query->post_filter->compile();
        // }

        // if (!empty($query->filterPath)) {
        //     $payload['filter_path'] = $query->filterPath;
        // }

        // if ($query->profile) {
        //     $payload['body']['profile'] = true;
        // }

        // $query->buildSuggests();

        return $payload;
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param string $seed
     *
     * @return string
     */
    public function compileRandom($seed)
    {
        return (new FunctionScore())->randomScore();
    }

    public function compileGet($from, $id, $columns)
    {
        return array_filter([
            'index' => $from,
            'id' => $id,
            '_source_includes' => $columns,
        ]);
    }

    public function compileFindMany($from, $ids, $columns)
    {
        return [
            'body' => [
                'docs' => collect($ids)
                    ->map(fn (string|int $id) => array_filter([
                        '_index' => $from,
                        '_id' => $id,
                        '_source' => $columns,
                    ]))
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function compileSelectMany($queries)
    {
        return [
            'body' => collect($queries)
                ->flatMap(fn (BaseBuilder|Relation|Builder $query) => [
                    ['index' => $query->from ?? $query->getQuery()->from],
                    match (true) {
                        $query instanceof Builder => $query->toBase()->toSql()['body'],
                        $query instanceof BaseBuilder => $query->toSql()['body'],
                        $query instanceof Relation => $query->getBaseQuery()->toSql()['body'],
                    },
                ])
                ->all(),
        ];
    }

    /**
     * Compile an update statement into SQL.
     *
     * @return string
     */
    public function compileUpdate(BaseBuilder $query, array|Script $values)
    {
        /** @var Builder $query */

        return [
            'index' => $query->index_id,
            'id' => $query->model_id,
            // 'refresh' => $refresh,
            'body' => match (true) {
                $values instanceof Script => array_filter([
                    'script' => $values->compile(),
                    '_source' => $query->columns,
                ]),
                is_array($values) => array_filter([
                    'doc_as_upsert' => true,
                    'doc' => Arr::except($values, ['_id', '_index']),
                    // '_source' => $query->columns
                ]),
            }
        ];
    }

    public function compileUpdateByQuery(BaseBuilder $query, Script $script)
    {
        return [
            'index' => $query->from,
            'body' => array_filter([
                // 'max_docs' => 10000,
                'script' => $script->compile(),
                'query' => $this->compileWhereComponents($query)->compile(),
            ]),
        ];
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @return string
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.

        /**  @var Builder $query*/
        return [
            'options' => [
                'ignore_conflicts' => $query->ignore_conflicts,
            ],
            'body' => collect($values)
                ->flatMap(static function (array $doc): array {
                    $index = Arr::pull($doc, '_index');
                    $id = Arr::pull($doc, '_id');

                    $method = empty($id) ? 'create' : 'index';

                    return [
                        [
                            $method => array_filter([
                                '_id' => $id,
                                '_index' => $index,
                            ]),
                        ],
                        $doc,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * Compile an exists statement into SQL.
     *
     * @return string
     */
    public function compileExists(BaseBuilder $query)
    {
        $payload = $this->compileSelect($query->take(0));
        $payload['terminate_after'] = 1;

        return $payload;
    }

    public function compileSuggestComponents(BaseBuilder $query): array
    {
        $suggest = [];
        if (!empty($query->suggest)) {
            foreach ($query->suggest as $suggestion) {
                $suggest[$suggestion['name']] = [
                    'text' => $suggestion['text'],
                    $suggestion['type'] => array_filter([
                        'field' => $suggestion['field'],
                        'size' => $suggestion['size'],
                        'sort' => $suggestion['sort'],
                        'suggest_mode' => $suggestion['mode'],
                        'min_doc_freq' => $suggestion['min_doc_freq'],
                    ]),
                ];
            }
        }

        return $suggest;
    }

    public function compileOrderComponents(BaseBuilder $query): array
    {
        $sorts = [];
        if (!empty($query->orders)) {
            foreach ($query->orders as $order) {
                if (!empty($order['type']) && 'Raw' == $order['type']) {
                    // throw new \Exception('TODO: allow raw arrays');
                    if (is_array($order['sql'])) {
                        $sorts[] = $order['sql'];
                    } elseif ($order['sql'] instanceof Query) {
                        $sorts[] = $order['sql']->compile();
                    }
                } else {
                    $sorts[] = [
                        (string) $order['column'] => array_filter([
                            'order' => $order['direction'],
                            'missing' => $order['missing'] ?? null,
                            'mode' => $order['mode'] ?? null,
                            'nested' => $order['nested'] ?? null,
                        ]),
                    ];
                }
            }
        }

        return $sorts;
    }

    public function compileWhereComponents(BaseBuilder $query): Query
    {
        $bool = Boolean::make();
        // dd($this->wheres);
        // WHERE Types
        // - Basic
        // - Nested
        // - In
        // - NotIn
        // - Null
        // - NotNull
        // - Between
        // - Date
        // - Month
        // - Day
        // - Year
        // - Time
        // - Raw

        $orWheres = collect($query->wheres)
            ->chunkWhile(fn ($where) => 'or' != $where['boolean']);

        foreach ($orWheres as $whereGroup) {
            $bool->should($groupBool = Boolean::make());
            foreach ($whereGroup as $where) {
                if ('raw' == $where['type']) {
                    if ($where['sql'] instanceof Query) {
                        $groupBool->must($where['sql']);
                    } elseif (is_array($where['sql'])) {
                        throw new \Exception('TODO: allow raw arrays');
                    }

                    continue;
                }

                if ('Nested' == $where['type']) {
                    $groupBool->must($where['query']->getGrammar()->compileWhereComponents($where['query']));

                    continue;
                }

                if (in_array($where['type'], ['Null', 'NotNull'])) {
                    $notNull = (new Exists(field: $where['column']));
                    if ('NotNull' == $where['type']) {
                        $groupBool->filter($notNull);
                    } else {
                        $groupBool->filter((new Boolean())->mustNot($notNull));
                    }

                    continue;
                }

                if (in_array($where['type'], ['Exists', 'NotExists'])) {
                    // dump($where, $query);

                    throw new \Exception('Elasticsearch does not support Exists/NotExists');
                }

                if (in_array($where['type'], ['FullText'])) {
                    throw new \Exception('TODO');
                }

                $field = $where['column'];

                if ('between' == $where['type']) {
                    $act = $where['not'] ? 'mustNot' : 'filter';

                    $groupBool->{$act}(
                        Boolean::make()
                            ->filter((new Range(field: $field))->gt($where['values'][0]))
                            ->filter((new Range(field: $field))->lt($where['values'][1]))
                    );

                    continue;
                }
                if ('In' == $where['type']) {
                    if (!empty($where['values'])) {
                        $groupBool->filter(
                            new Terms(
                                field: $where['column'],
                                values: array_values($where['values'])
                            )
                        );
                    }

                    continue;
                }
                if ($where['type'] == 'NotIn') {
                    if (!empty($where['values'])) {
                        $groupBool->mustNot(
                            new Terms(
                                field: $where['column'],
                                values: array_values($where['values'])
                            )
                        );
                    }

                    continue;
                }
                // if (empty($where['operator'])) {
                //     dd($where);
                // }

                $operator = $where['operator'];
                $value = $where['value'];


                if (is_object($value)) {
                    if ($value instanceof BackedEnum) {
                        $value = $value->value;
                    } elseif (enum_exists($value)) {
                        $value = $value->name;
                    }
                }


                match ($where['operator']) {
                    '>' => $groupBool->filter((new Range(field: $field))->gt($value)),
                    '>=' => $groupBool->filter((new Range(field: $field))->gte($value)),
                    '<' => $groupBool->filter((new Range(field: $field))->lt($value)),
                    '<=' => $groupBool->filter((new Range(field: $field))->lte($value)),
                    '<>' => $groupBool->filter(
                        Boolean::make()->mustNot(new Term(field: $field, value: $value))
                    ),
                    '=' => match (is_array($value)) {
                        true => $groupBool->must(new Terms(field: $field, values: $value)),
                        false => $groupBool->must(new Term(field: $field, value: $value)),
                    },
                    // 'like' => $groupBool->must(( Term::make())->field($field)->value(trim(strtolower($value), '%'))),
                    // 'like' => $groupBool->must(( Wildcard::make())->field($field)->value(trim(strtolower($value), '%'))),
                    'like' => $groupBool->must(new Prefix(field: $field, value: trim($value, '%'))),
                    // 'like' => $groupBool->must(Term::make()->field($field)->value(trim($value, '%'))),
                    'rank' => $groupBool->should(new RankFeature(field: $field, boost: $value)),
                };
            }
        }

        // dump($bool);

        return $bool;
    }

    public function compileUpsert(BaseBuilder $query, array $values, array $uniqueBy, array|null $update)
    {

        /** @var Builder $query */

        return [
            'options' => [
                'ignore_conflicts' => $query->ignore_conflicts,
            ],
            'body' => collect($values)
                ->flatMap(static function (array $val, int $i) use ($update): array {
                    $id = Arr::pull($val, '_id');
                    if (empty($id)) {
                        throw new \Exception('All upserts must have an _id');
                    }

                    $header = [
                        'update' => [
                            '_id' => $id,
                            '_index' => Arr::pull($val, '_index'),
                        ],
                    ];

                    $body = match (true) {
                        empty($update) => [
                            'doc' => empty($val) ? new stdClass() : $val,
                            'doc_as_upsert' => true,
                        ],
                        $update[$i] instanceof Script => [
                            'scripted_upsert' => true,
                            'script' => $update[$i]->compile(),
                            'upsert' => $val,
                        ],
                        default => throw new \Exception('TODO'),
                    };

                    return [$header, $body];
                })
                ->all(),
        ];
    }


    public function compileBulkOperation(
        BaseBuilder $query,
        iterable $models,
        string $operation,
        null|array $scripts = null,
        bool $doc_as_upsert = false,
        bool $scripted_upsert = false
    ): array {
        /** @var Builder $query */

        if (!in_array($operation, ['create', 'index', 'update', 'delete'])) {
            throw new Exception("Invalid Elastic operation [$operation]");
        }
        if ($scripts) {
            if (($operation !== 'update')) {
                throw new Exception('Script can only be used with update operation');
            }
            if (count($scripts) !== count($models)) {
                throw new Exception('There must be a script for each model');
            }
        }


        return [
            'options' => [
                'ignore_conflicts' => $query->ignore_conflicts,
            ],
            'body' => collect($models)
                ->flatMap(static function (object $model, $i) use ($query, $operation, $scripts, $doc_as_upsert, $scripted_upsert): array {
                    $id = is_string($model) ? $model : $model['_id'];

                    $document = Arr::except($model, ['_id', '_index']) ?: new stdClass();

                    $header = [
                        $operation => [
                            '_id' => $id,
                            '_index' => $model['_index'] ?? $query->from,
                        ],
                    ];
                    if ($operation === 'delete') {
                        return [$header];
                    }

                    if (!empty($scripts)) {
                        return [$header, [
                            'scripted_upsert' => $scripted_upsert,
                            'script' => $scripts[$i]->compile(),
                            'upsert' => $document,
                        ]];
                    }

                    return [$header, [
                        'doc_as_upsert' => $doc_as_upsert,
                        'doc' => $document,
                    ]];
                })
                ->all()
        ];
    }
}
