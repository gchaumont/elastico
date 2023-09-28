<?php

namespace Elastico\Scripting;

/** 
 *  Abstract class for all scripts
 */
class Increment extends Script
{

    public function __construct(
        protected string $field,
        protected int $amount = 1,
        protected null|array $extra = null,
    ) {
    }

    public function source(): null|string
    {
        # increment the field by the amount and add the extra values
        # handle the case of nested and missing fields

        return <<<PAINLESS
            if (ctx._source.containsKey(params.field)) {
                ctx._source[params.field] += params.value;
            } else {
                ctx._source[params.field] = params.value;
            }
            if (params.extra != null) {
                ctx._source.putAll(params.extra);
            }
            PAINLESS;
    }

    public function parameters(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->amount,
            'extra' => $this->extra,
        ];
    }
}
