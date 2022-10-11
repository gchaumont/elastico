<?php

namespace Elastico\Models\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany extends Has
{
    public function getResults(): iterable|object
    {
        return $this->get();
    }
}
