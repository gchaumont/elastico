<?php

namespace Elastico;

use Elastic\Elasticsearch\Client;

interface ConnectionResolverInterface
{
    public function __construct(array $connections);

    public function connection(string $name = null): ?Connection;

    /**
     * Add a connection to the resolver.
     */
    public function addConnection(string $name, Client $connection);

    /**
     * Check if a connection has been registered.
     */
    public function hasConnection(string $name): bool;

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): static;
}
