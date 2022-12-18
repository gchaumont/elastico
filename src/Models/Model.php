<?php

namespace Elastico\Models;

use Elastico\Connection;
// use Elastico\ConnectionResolverInterface;
use Elastico\Models\Features\BatchPersistable;
use Elastico\Models\Features\Configurable;
use Elastico\Models\Features\Persistable;
use Elastico\Models\Features\Queryable;
use Elastico\Models\Relations\Relatable;
use Http\Promise\Promise;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Reads and Writes Objects to the Database.
 */
abstract class Model extends DataAccessObject // implements Serialisable
{
    use BatchPersistable;
    use Configurable;
    use Persistable;
    use Queryable;
    // use Relatable;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    // public readonly string $id;

    public readonly string $_index;

    // public readonly string $_version;

    // public readonly string $_seq_no;

    // public readonly string $_primary_term;

    protected static string $_connection = 'default';

    protected static ConnectionResolverInterface $_resolver;

    public function initialiseIdentifiers(string $id, null|string $index = null): static
    {
        $this->setKey($id);

        if ($index) {
            $this->_index = $index;
        }

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getKeyName(): string
    {
        // get attribute name with Attribute ID
        return 'id';
    }

    public function setKey(string|int $key): static
    {
        return $this->setAttribute(
            attribute: $this->getKeyName(),
            value: (string) $key
        );
    }

    public function set_index(string|null $index): static
    {
        $this->_index = $index;

        return $this;
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

    public static function getConnectionName(): string
    {
        return static::$_connection;
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
            ->setKey($document['_id'] ?? $document['id'])
            ->set_index($document['_index'] ?? null)
        ;
    }
}
