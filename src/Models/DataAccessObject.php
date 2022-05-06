<?php

namespace Gchaumont\Models;

use Gchaumont\Models\Features\Mappable;
use Gchaumont\Models\Features\Serialisable;
use Gchaumont\Models\Features\Unserialisable;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject
{
    use Mappable;
    use Serialisable;
    use Unserialisable;
}
