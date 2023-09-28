<?php

namespace Elastico\Scripting;

use Illuminate\Support\Arr;
use Elastico\Eloquent\Model;

/** 
 *  Abstract class for all scripts
 */
class UpdateParams extends Script
{
    public function __construct(
        protected array $params = null,
    ) {
    }

    public function source(): null|string
    {
        # increment the field by the amount and add the extra values
        # handle the case of nested and missing fields

        // return "ctx._source.{$this->field} += params.value; 
        return <<<PAINLESS
            ctx._source.putAll(params.values);
            PAINLESS;
    }

    public function parameters(): array
    {
        $values = $this->model instanceof Model ? $this->model->getAttributes() : $this->model;

        if ($this->params === null) {
            return $values;
        }

        return Arr::only($values, $this->params);
    }
}
