<?php

namespace Gchaumont\Models;

use Gchaumont\Models\Features\BatchPersistable;
use Gchaumont\Models\Features\Configurable;
use Gchaumont\Models\Features\Persistable;
use Gchaumont\Models\Features\Queryable;
use Gchaumont\Models\Features\Relatable;

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

    public readonly string $_id;

    public readonly string $_index;

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

    public function make_id(): ?string
    {
        return null;
    }
}
