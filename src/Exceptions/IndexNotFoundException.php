<?php

namespace Elastico\Exceptions;

use Exception;

class IndexNotFoundException extends Exception
{
    public function __construct(public string $index)
    {
        parent::__construct("Index [{$index}] not found");
    }
}
