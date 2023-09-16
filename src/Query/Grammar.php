<?php

namespace Elastico\Query;

use Elastico\Query\Compound\Boolean;
use Elastico\Query\Compound\FunctionScore;
use Elastico\Query\Specialized\RankFeature;
use Elastico\Query\Term\Exists;
use Elastico\Query\Term\Prefix;
use Elastico\Query\Term\Range;
use Elastico\Query\Term\Term;
use Elastico\Query\Term\Terms;
use Elastico\Query\Term\Wildcard;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Arr;
use Enum;
use BackedEnum;
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
        return [
            'body' => collect($ids)
                ->flatMap(fn ($val) => [
                    [
                        'delete' => [
                            '_id' => $val,
                            '_index' => $query->from,
                        ],
                    ],
                    // [
                    //     // 'update' => [
                    //     // 'doc' => $val,
                    //     // 'doc_as_upsert' => true,
                    //     // ],
                    // ],
                ])
                ->all(),
        ];
    }

    public function buildPayload(BaseBuilder $query): array
    {
        $payload['index'] = $query->from;

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

        if (!empty($query->columns)) {
            $payload['body']['_source']['includes'] = $query->columns;
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
                ->flatMap(fn ($query) => [
                    ['index' => $query->from ?? $query->getQuery()->from],
                    $query->toSql()['body'],
                ])
                ->all(),
        ];
    }

    /**
     * Compile an update statement into SQL.
     *
     * @return string
     */
    public function compileUpdate(BaseBuilder $query, array $values)
    {
        $index = $values['_index'] ?? $query->from;

        $id = $values['_id'] ?? $query->model_id ?? throw new \Exception('No ID found for update statement');
        unset($values['_index'], $values['_id']);

        return [
            'index' => $index,
            'id' => $id,
            // 'refresh' => $refresh,
            'body' => array_filter([
                'doc_as_upsert' => true,
                'doc' => $values,
                // '_source' => $source,
            ]),
        ];
    }

    public function compileUpdateByQuery(BaseBuilder $query, array $values)
    {
        $index = $values['_index'] ?? $query->from;

        unset($values['_index']);

        return [
            'index' => $index,
            'body' => array_filter([
                // 'max_docs' => 10000,
                'script' => [
                    'source' => $this->buildPainlessScriptSource('', $values),
                    'lang' => 'painless',
                    'params' => $values,
                ],
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
        return [
            'body' => collect($values)
                ->flatMap(static fn (array $doc): array => [
                    [
                        !empty($doc['_id']) ? 'index' : 'create' => array_filter([
                            '_id' => Arr::pull($doc, '_id'),
                            '_index' => Arr::pull($doc, '_index'),
                        ]),
                    ],
                    $doc,
                ])
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

    public function compileSuggestComponents(Builder $query): array
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

    public function compileOrderComponents(Builder $query): array
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

    public function compileWhereComponents(Builder $query): Query
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
                // if (empty($where['operator'])) {
                //     dd($where);
                // }

                $operator = $where['operator'];
                $value = $where['value'];

                # if value is an BackedEnum then we need to get the value, if is Enum then we need to get the key
                if ($value instanceof BackedEnum) {
                    $value = $value->value();
                } elseif ($value instanceof Enum) {
                    $value = $value->key();
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

    public function compileUpsert(BaseBuilder $query, array $values, array $uniqueBy, array $update)
    {
        return [
            'body' => collect($values)
                ->flatMap(fn ($val) => [
                    [
                        'update' => [
                            '_id' => Arr::pull($val, '_id'),
                            '_index' => Arr::pull($val, '_index'),
                        ],
                    ],
                    [
                        // 'update' => [
                        'doc' => $val,
                        'doc_as_upsert' => true,
                        // ],
                    ],
                ])
                ->all(),
        ];
    }

    private function buildPainlessScriptSource(string $prefix, array $fields): string
    {
        $source = '';

        foreach ($fields as $field => $value) {
            if (is_array($value)) {
                $source .= $this->buildPainlessScriptSource($prefix . $field . '.', $value);
            } else {
                $source .= 'ctx._source.' . $prefix . $field . ' = params.' . $prefix . $field . ";\n";
            }
        }

        return $source;
    }
}
