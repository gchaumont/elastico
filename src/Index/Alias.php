<?php

namespace Elastico\Index;

use Elastico\Query\Query;

class Alias
{
    public function __construct(
        protected ?Query $filter = null,
        protected ?string $index_routing = null,
        protected ?bool $is_hidden = null,
        protected ?bool $is_write_index = null,
        protected ?string $routing = null,
        protected ?string $search_routing = null,
    ) {
    }
}
