<?php

namespace Elastico\Scripting;

use Elastico\Eloquent\Model;

/** 
 *  Abstract class for all scripts
 */
class Script
{
    public array|Model $model;

    public function __construct(
        public string $source,
        public array $params = [],
    ) {
    }


    public function lang(): string
    {
        return 'painless';
    }

    public function id(): null|string
    {
        return null;
    }

    public function source(): null|string
    {
        return $this->source;
    }

    public function parameters(): array
    {
        return $this->params;
    }

    public function withModel(array|Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function compile(): array
    {
        return array_filter([
            'lang' => $this->lang(),
            'source' => $this->source(),
            'params' => $this->parameters(),
        ]);
    }
}
