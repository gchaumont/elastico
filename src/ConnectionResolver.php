<?php

namespace Elastico;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

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
        foreach ($connections as $name => $connection) {
            $this->addConnection(
                name: $name,
                client: ClientBuilder::fromConfig($connection)->setAsync(false)
            );
        }
    }

    public function connection(string $name = null): ?Connection
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

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
    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }
}
