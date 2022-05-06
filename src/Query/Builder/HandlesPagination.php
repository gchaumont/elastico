<?php

namespace Gchaumont\Query\Builder;

trait HandlesPagination
{
    protected int $skip = 0;

    protected ?int $take = null;

    public function take(int $count): self
    {
        $this->take = $count;

        return $this;
    }

    public function skip(int $count): self
    {
        $this->skip = $count;

        return $this;
    }
}
