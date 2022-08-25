<?php

namespace Elastico;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;

class ConnectionResolver implements ConnectionResolverInterface
{
    /**
     * All registered connections.
     */
    protected array $connections = [];

    /**
     * Default connection name.
     */
    protected string $default;

    public function __construct(array $connections)
    {
        collect($connections)
            ->map(fn ($config) => $this->createClientConfigFromConnection($config))
            ->each(fn ($connection, $name) => $this->addConnection(
                name: $name,
                client: ClientBuilder::fromConfig($connection)
            ))
        ;
    }

    public function connection(string $name = null): ?Connection
    {
        $name ??= $this->getDefaultConnection();

        return $this->connections[$name];
    }

    /**
     * Add a connection to the resolver.
     */
    public function addConnection(string $name, Client $client)
    {
        $this->connections[$name] = new Connection($name, $client);
    }

    /**
     * Check if a connection has been registered.
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): static
    {
        $this->default = $name;

        return $this;
    }

    private function createClientConfigFromConnection(array $connection): array
    {
        return $connection['client'] ?? [] + array_filter([
            'basicAuthentication' => array_filter([
                'username' => $connection['username'] ?? null,
                'password' => $connection['password'] ?? null,
            ]),
            'hosts' => $connection['hosts'] ?? null,
            //'CABundle' => storage_path('/elastic/certificate.crt'),
            'AsyncHttpClient' => $connection['client']['AsyncHttpClient'] ?? GuzzleAdapter::createWithConfig(array_filter(['verify' => $connection['CABundle'] ?? null])),
            'ElasticCloudId' => $connection['cloud'] ?? null,
        ]);
    }
}
