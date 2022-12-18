<?php

namespace Elastico;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastico\Query\Builder;
use Exception;
use GuzzleHttp\Promise\Promise;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\LazyCollection;

/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class Connection extends BaseConnection implements ConnectionInterface
{
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
        $identifier = $method.'.'.rand(0, 10000000);

        $this->startingQuery(endpoint: $method, identifier: $identifier);

        $response = $this->getClient()->{$method}($payload);

        if ($response instanceof Promise) {
            $handlePromise = fn ($response) => $this->endingQuery(
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
            throw new \RuntimeException('Unsupported Elasticsearch Response');
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
        // throw new Exception('Error Processing Request', 1);

        $query = [
            'method' => 'search',
            'payload' => $query,
        ];

        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            return $this->performQuery($query['method'], $query['payload']);
            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared(
                $this->getPdoForSelect($useReadPdo)->prepare($query)
            );

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                ->prepare($query));

            $this->bindValues(
                $statement,
                $this->prepareBindings($bindings)
            );

            // Next, we'll execute the query against the database and return the statement
            // so we can return the cursor. The cursor will use a PHP generator to give
            // back one row at a time without using a bunch of memory to render them.
            $statement->execute();

            return $statement;
        });

        while ($record = $statement->fetch()) {
            yield $record;
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
        return $this->statement($query, $bindings);
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
        return $this->affectingStatement($query, $bindings);
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
     * @param string     $query
     * @param array      $bindings
     * @param null|float $time
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->totalQueryDuration += $time ?? 0.0;
        $bindings = [];
        $this->event(new QueryExecuted(json_encode($query), $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
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
     * Run a SQL statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, \Closure $callback)
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
        catch (\Exception $e) {
            throw new QueryException(
                json_encode($query),
                $this->prepareBindings($bindings),
                $e
            );
        }
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     */
    protected function reconnectIfMissingConnection()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
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
