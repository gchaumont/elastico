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
        unset($compiled['body']['sort']);

        return $compiled;
    }

    public function compileDelete(BaseBuilder $query)
    {
        return $this->compileSelect($query);
    }

      public function buildPayload(BaseBuilder $query): array
      {
          $payload['index'] = $query->from;

          $payload['body']['from'] = $query->offset ?? null;

          $payload['body']['size'] = $query->limit ?? null;

          $payload['body']['query'] = $this->compileWhereComponents($query)->compile();

          $payload['body']['sort'] = $this->compileOrderComponents($query);

          $payload['body']['post_filter'] = $query->post_filter?->compile();

          if (!empty($query->columns)) {
              $payload['body']['_source']['includes'] = $query->columns;
          }

          $payload['body']['aggs'] = $query->getAggregations()
              ->map(fn ($agg) => $agg->compile())
              ->all()
          ;

          $payload['body'] = collect($payload['body'])
              ->reject(fn ($part) => null === $part)
              ->reject(fn ($part) => [] === $part)
              ->all()
          ;

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

          // $query->buildRanks();

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
                    ['index' => $query->from],
                    $query->toSql()['body'],
                ])
                ->all(),
        ]
        ;
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
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(function ($record) {
            return '('.$this->parameterize($record).')';
        })->implode(', ');

        return "insert into {$table} ({$columns}) values {$parameters}";
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
                        (string) $order['column'] => [
                            'order' => $order['direction'],
                            // 'missing' => null,
                            // 'mode' => null,
                            // 'nested' => null,
                        ],
                    ];
                }
            }
        }

        return $sorts;
    }

    public function compileWhereComponents(Builder $query): Query
    {
        $bool = new Boolean();
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
            ->chunkWhile(fn ($where) => 'or' != $where['boolean'])
        ;

        foreach ($orWheres as $whereGroup) {
            $bool->should($groupBool = new Boolean());
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
                    $groupBool->filter($where['query']->getGrammar()->compileWhereComponents($where['query']));

                    continue;
                }

                if (in_array($where['type'], ['Null', 'NotNull'])) {
                    $notNull = (new Exists())->field($where['column']);
                    if ('NotNull' == $where['type']) {
                        $groupBool->filter($notNull);
                    } else {
                        $groupBool->filter((new Boolean())->mustNot($notNull));
                    }

                    continue;
                }

                if (in_array($where['type'], ['Exists', 'NotExists'])) {
                    dump($where, $query);

                    throw new \Exception('TODO');
                }

                if (in_array($where['type'], ['FullText'])) {
                    throw new \Exception('TODO');
                }

                $field = $where['column'];

                if ('between' == $where['type']) {
                    $act = $where['not'] ? 'mustNot' : 'filter';

                    $groupBool->{$act}(
                        (new Boolean())
                            ->filter((new Range())->field($field)->gt($where['values'][0]))
                            ->filter((new Range())->field($field)->lt($where['values'][1]))
                    );

                    continue;
                }
                if ('In' == $where['type']) {
                    if (!empty($where['values'])) {
                        $groupBool->filter((new Terms())->field($where['column'])->values($where['values']));
                    }

                    continue;
                }
                // if (empty($where['operator'])) {
                //     dd($where);
                // }

                $operator = $where['operator'];
                $value = $where['value'];

                match ($where['operator']) {
                    '>' => $groupBool->filter((new Range())->field($field)->gt($value)),
                    '>=' => $groupBool->filter((new Range())->field($field)->gte($value)),
                    '<' => $groupBool->filter((new Range())->field($field)->lt($value)),
                    '<=' => $groupBool->filter((new Range())->field($field)->lte($value)),
                    '<>' => $groupBool->filter(
                        (new Boolean())->mustNot((new Term())->field($field)->value($value))
                    ),
                    '=' => match (is_array($value)) {
                        true => $groupBool->filter((new Terms())->field($field)->values($value)),
                        false => $groupBool->filter((new Term())->field($field)->value($value)),
                    },
                    // 'like' => $groupBool->must((new Term())->field($field)->value(trim(strtolower($value), '%'))),
                    // 'like' => $groupBool->must((new Wildcard())->field($field)->value(trim(strtolower($value), '%'))),
                    'like' => $groupBool->must((new Prefix())->field($field)->value(trim($value, '%'))),
                    'rank' => $groupBool->should((new RankFeature())->field($field)->boost($value)),
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
         ]
         ;
     }
}
