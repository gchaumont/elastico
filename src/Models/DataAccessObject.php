<?php

namespace Elastico\Models;

use Elastico\Models\Features\Mappable;
use Elastico\Models\Features\Serialisable;
use Elastico\Models\Features\Unserialisable;

/**
 * Serialises Data Objects from and to the Database.
 */
abstract class DataAccessObject
{
    use Mappable;
    use Serialisable;
    use Unserialisable;
}
