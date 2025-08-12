<?php

namespace Elastico;

use RuntimeException;
use Generator;
use Closure;
use Elastico\Query\Processor;
use Elastico\Query\Grammar;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastico\Eloquent\Model;
use Elastico\Exceptions\BulkException;
use Elastico\Exceptions\IndexNotFoundException;
use Elastico\Query\Builder;
use Elastico\Query\Response\Collection;
use Exception;
use GuzzleHttp\Promise\Promise;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\LazyCollection;

/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class Connection extends BaseConnection implements ConnectionInterface
{
    const DEFAULT_CURSOR_SIZE = 1000;

    protected $client;

    public function __construct($config)
    {
        $this->config = $config;

        $this->database = $config['database'] ?? null;

        $this->useDefaultPostProcessor();

        $this->useDefaultQueryGrammar();
    }

    public function setAsync(bool $async): static
    {
        $this->getClient()->setAsync($async);

        return $this;
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return new Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'elastic';
    }

    /**
     * Custom Function.
     *
     * @param mixed $method
     * @param mixed $payload
     */
    public function performQuery($method, $payload)
    {
        return $this->getClient()->{$method}($payload);
        $identifier = $method . '.' . rand(0, 10000000);

        $this->startingQuery(endpoint: $method, identifier: $identifier);

        $response = $this->getClient()->{$method}($payload);

        if ($response instanceof Promise) {
            $handlePromise = fn($response) => $this->endingQuery(
                method: $method,
                payload: $payload,
                response: json_decode((string) $response->getBody(), true),
                identifier: $identifier
            );
            $response->then($handlePromise, $handlePromise);
        } elseif ($response instanceof Elasticsearch) {
            $response = json_decode((string) $response->getBody(), true);

            $this->endingQuery(
                method: $method,
                payload: $payload,
                response: $response,
                identifier: $identifier
            );
        } else {
            throw new RuntimeException('Unsupported Elasticsearch Response');
        }

        return $response;
    }

    public function find($query)
    {
        $query = [
            'method' => 'get',
            'payload' => $query,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['payload']);
        });
    }

    public function findMany($query)
    {
        if (collect($query['body']['docs'])->isEmpty()) {
            return new LazyCollection();
        }

        $query = [
            'method' => 'mget',
            'query' => $query,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['query']);
        });
    }

    public function count($query)
    {
        $query = [
            'method' => 'count',
            'query' => $query,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['query']);
        });
    }

    public function bulk($query)
    {
        $query = [
            'method' => 'bulk',
            'payload' => $query,
            'options' => $query['options']
        ];

        unset($query['payload']['options']);

        return $this->run($query, [], function ($query, $bindings) {
            $response = $this->performQuery($query['method'], $query['payload']);

            if ($response['errors'] ?? false) {
                if ($query['options']['ignore_conflicts']) {
                    $hasOtherErrors = collect($response['items'])
                        ->filter(fn(array $item): bool => !empty(collect($item)->first()['error']))
                        ->filter(fn(array $item): bool => collect($item)->first()['error']['type'] != 'version_conflict_engine_exception')
                        ->isNotEmpty();

                    if (!$hasOtherErrors) {
                        return $response;
                    }
                }
                throw new BulkException(json_encode($response->asArray()));
            }

            return $response;
        });
    }

    public function termsEnum(string|array $index, string $field, ?int $size = null, ?string $string = null, ?string $after = null, ?bool $insensitive = null)
    {
        $query = [
            'method' => 'termsEnum',
            'payload' => [
                'index' => $index,
                'body' => array_filter([
                    'field' => $field,
                    'size' => $size,
                    'string' => $string,
                    'search_after' => $after,
                    'case_insensitive' => $insensitive,
                ]),
            ],
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['payload']);
        });
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = [
            'method' => 'search',
            'payload' => $query,
        ];

        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            return $this->performQuery($query['method'], $query['payload']);
        });
    }

    public function selectMany($queries)
    {
        $query = [
            'method' => 'msearch',
            'payload' => $queries,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $responses = $this->performQuery($query['method'], $query['payload']);
            $responses = $this->getPostProcessor()->resolvePromise($responses);

            foreach ($responses['responses'] as $response) {

                if ($response['error'] ?? false) {
                    throw new QueryException(
                        $this->getDriverName(),
                        mb_substr(json_encode($query), 0, 500),
                        $this->prepareBindings($bindings),
                        new Exception(mb_substr(json_encode($response['error']), 0, 500)) // new Exception(($response['error']['reason'] ?? substr(json_encode($response['error']), 0, 100)) . ': ' . ($response['error']['root_cause'][0]['reason'] ?? ''))
                    );
                }
            }
            return $responses;
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     * @param mixed  $keepAlive
     */
    public function cursor($query, $bindings = [], $useReadPdo = true, $keepAlive = '1m'): Generator
    {
        $total = null;
        $payload = null;

        $response = $this->run($query, $bindings, function ($query, $bindings) use (&$total, $keepAlive, &$payload) {
            if ($this->pretending()) {
                return [];
            }

            $payload = $query;
            // $payload['scroll'] = $seconds.'s';
            $payload['body']['size'] ??= static::DEFAULT_CURSOR_SIZE;
            $payload['body']['sort'] ??= '_shard_doc';


            $pit = $this->performQuery('openPointInTime', [
                'index' => $payload['index'],
                'keep_alive' => $keepAlive,
            ]);

            if ($pit instanceof Promise) {
                $pit = $pit->wait()->asArray();
            }
            if ($pit instanceof Elasticsearch) {
                $pit = $pit->asArray();
            }

            $pit['keep_alive'] = $keepAlive;
            unset($pit['_shards']);

            $payload['body']['pit'] = $pit;
            unset($payload['index']);

            return $this->performQuery('search', $payload);
        });

        yield from $response['hits']['hits'];

        $total = $response['hits']['total']['value'];

        while ($total) {
            // if (!empty($query['body']['query'])) {
            //     $payload['body']['query'] = $query['body']['query'];
            // }
            $payload['body']['pit']['id'] = $response['pit_id'];
            $payload['body']['search_after'] = $response['hits']['hits'][count($response['hits']['hits']) - 1]['sort'];
            // $payload['body']['size'] ??= 1000;
            // $payload['body']['sort'] ??= '_shard_doc';

            $response = $this->performQuery('search', $payload);

            if ($response instanceof Promise) {
                $response = $response->wait()->asArray();
            }
            if ($response instanceof Elasticsearch) {
                $response = $response->asArray();
            }

            yield from (new Collection(
                items: $response['hits']['hits'],
                total: count($response['hits']['hits']),
                aggregations: [],
                response: $response,
                // query: $query,
            ))
                ->tap(function ($hits) use (&$total) {
                    $total = $hits->count();
                })
                ->keyBy(fn($hit) => $hit instanceof Model  ? $hit->getKey() : $hit['_id'])
                ->all();

            // yield from (new PromiseResponse(
            //     source: fn ($r): array => $r['hits']['hits'],
            //     total: fn ($r): int => count($r['hits']['total']),
            //     aggregations: fn ($r): array => [],
            //     response: $response,
            //     // query: $query
            // ))
            // ->tap(function ($hits) use (&$total) {
            //     $total = $hits->count();
            // })
            // ->keyBy(fn ($hit) => $hit instanceof Model ? $hit->getKey() : $hit['_id'])
            //     ->all();
        }

        if (isset($response['pit_id'])) {
            // $this->getConnection()->performQuery('clearScroll', ['scroll_id' => $response['_scroll_id']]);

            $this->performQuery('closePointInTime', [
                'body' => ['id' => $response['pit_id']],
            ]);
        }
    }

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function insert($query, $bindings = [])
    {

        $r =  $this->statement($query, $bindings);

        return $r;
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function update($query, $bindings = [])
    {
        $query = [
            'method' => 'update',
            'payload' => $query,
        ];

        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $response = $this->performQuery($query['method'], $query['payload']);

            if ($response instanceof Promise) {
                $response = $response->wait()->asArray();
            }
            $this->recordsHaveBeenModified(
                'updated' == $response['result']
            );

            return 1;
        });
    }

    public function updateByQuery($query)
    {
        $query = [
            'method' => 'updateByQuery',
            'payload' => $query,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $response = $this->performQuery($query['method'], $query['payload']);

            if ($response instanceof Promise) {
                $response = $response->wait()->asArray();
            }

            $this->recordsHaveBeenModified(
                'updated' == $response['updated']
            );

            return $response['updated'];
        });
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        $query['slices'] = 'auto';

        $query = [
            'method' => 'deleteByQuery',
            'payload' => $query,
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['payload']);
        });
    }

    public function deleteDocument(string $id, string $index)
    {
        $query = [
            'method' => 'delete',
            'payload' => [
                'index' => $index,
                'id' => $id,
            ],
        ];

        return $this->run($query, [], function ($query, $bindings) {
            return $this->performQuery($query['method'], $query['payload']);
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );

            return $count;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param string $query
     *
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = false !== $this->getPdo()->exec($query)
            );

            return $change;
        });
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param string|array     $query
     * @param array      $bindings
     * @param null|float $time
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->totalQueryDuration += $time ?? 0.0;
        $bindings = [];


        $cleanedQuery = static::cleanQuery($query);

        $queryString = json_encode($cleanedQuery);

        if (!app()->isLocal()) {
            $queryString = mb_substr($queryString, 0, 1000);
        }

        $this->event(new QueryExecuted(
            $queryString,
            $bindings,
            $time,
            $this
        ));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    private static function cleanQuery(array $query): array
    {
        # cleanup query by recursively reducing all arrays to max 20 items and add ... 
        # and all strings to max 1000 chars
        $cleanedQuery = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $cleanedQuery[$key] = static::cleanQuery($value);
                if (count($cleanedQuery[$key]) > 20) {
                    $cleanedQuery[$key] = array_slice($cleanedQuery[$key], 0, 20);
                    $cleanedQuery[$key][] = '...';
                }
            } elseif (is_string($value)) {
                $cleanedQuery[$key] = mb_substr($value, 0, 1000);
            } else {
                $cleanedQuery[$key] = $value;
            }
        }

        return $cleanedQuery;
    }

    public function getClient(): Client
    {
        return $this->client ??= ClientBuilder::fromConfig(
            $this->createClientConfigFromConnection($this->config)
        )
            // ->setAsync($this->config['async'] ?? false)
        ;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     */
    public function reconnectIfMissingConnection() {}

    /**
     * Run a SQL statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     *
     * @throws QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            return $callback($query, $bindings);
        }

        // If an exception occurs when attempting to run a query, we'll format the error
        // message to include the bindings with SQL, which will make this exception a
        // lot more helpful to the developer instead of just the database's errors.
        catch (Exception $e) {
            if (str_contains($e->getMessage(), 'index_not_found_exception')) {
                if (preg_match('/no such index \[(.*?)\]/', $e->getMessage(), $matches)) {
                    $index_name = $matches[1];
                } else {
                    $index_name = 'unknown';
                }

                throw new IndexNotFoundException(index: $index_name);
            }
            if (str_starts_with($e->getMessage(), '404 Not Found')) {

                throw new ModelNotFoundException();
            }

            throw new QueryException(
                $this->getConfig('name'),
                mb_substr(json_encode($query), 0, 500),
                $this->prepareBindings($bindings),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar()
    {
        return new Grammar();
    }

    private function createClientConfigFromConnection(array $connection): array
    {
        return ($connection['client'] ?? []) + array_filter([
            'basicAuthentication' => array_filter([
                'username' => $connection['username'] ?? null,
                'password' => $connection['password'] ?? null,
            ]),
            'hosts' => $connection['hosts'] ?? null,
            'CABundle' => $connection['certificate'] ?? null,
            'AsyncHttpClient' => $connection['client']['AsyncHttpClient'] ?? GuzzleAdapter::createWithConfig(array_filter(['verify' => $connection['certificate'] ?? null])),
            'ElasticCloudId' => $connection['cloud'] ?? null,
        ]);
    }
}
