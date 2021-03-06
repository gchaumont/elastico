<?php

namespace Elastico\Models;

use Elastico\Connection;
use Elastico\ConnectionResolverInterface;
use Elastico\Models\Features\BatchPersistable;
use Elastico\Models\Features\Configurable;
use Elastico\Models\Features\Persistable;
use Elastico\Models\Features\Queryable;
use Elastico\Models\Features\Relatable;
use Http\Promise\Promise;

/**
 * Reads and Writes Objects to the Database.
 */
abstract class Model extends DataAccessObject // implements Serialisable
{
    use BatchPersistable;
    use Configurable;
    use Persistable;
    use Queryable;
    use Relatable;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    public readonly string $_id;

    public readonly string $_index;

    protected static string $_connection = 'default';

    protected static ConnectionResolverInterface $_resolver;

    public function initialiseIdentifiers(string $id, null|string $index = null): static
    {
        $this->_id = $id;

        if ($index) {
            $this->_index = $index;
        }

        return $this;
    }

    final public function get_id(): ?string
    {
        return $this->_id ?? $this->make_id();
    }

    public function set_id(string|int $id): static
    {
        $this->_id = (string) $id;

        return $this;
    }

    public function has_id(): bool
    {
        return !empty($this->get_id());
    }

    public function make_id(): ?string
    {
        return null;
    }

    public static function setConnection(string $connection): static
    {
        static::$_connection = $connection;

        return static::class;
    }

    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        static::$_resolver = $resolver;
    }

    public static function getConnection(): Connection
    {
        return static::$_resolver->connection(static::$_connection);
    }

    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT;
    }

    public function getUpdatedAtColumn(): string
    {
        return static::UPDATED_AT;
    }

    public static function unserialise(array|Promise $document): static
    {
        return parent::unserialise($document)
            ->initialiseIdentifiers(id: $document['_id'] ?? $document['id'], index: $document['_index'] ?? null)
        ;
    }
}
