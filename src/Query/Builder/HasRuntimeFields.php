<?php

namespace Elastico\Query\Builder;

use Elastico\Query\Builder;
use Elastico\Mapping\RuntimeField;
use Illuminate\Support\Collection;

/** 
 * @mixin Builder
 */
trait HasRuntimeFields
{
    protected Collection $runtime_fields;

    public function getRuntimeFields(): Collection
    {
        return $this->runtime_fields ??= collect();
    }

    public function getRuntimeField(string $name): null|RuntimeField
    {
        return $this->getRuntimeFields()->get($name);
    }

    public function runtimeField(string $name, RuntimeField $runtime_field): self
    {
        $this->getRuntimeFields()->put($name, $runtime_field);

        return $this;
    }

    public function addRuntimeFields(iterable $runtime_fields): self
    {
        collect($runtime_fields)
            ->map(fn (RuntimeField $runtime_field, string $name) => $this->runtimeField($name, $runtime_field));

        return $this;
    }

    public function runtimeFields(iterable $runtime_fields): self
    {
        $this->runtime_fields = collect();

        $this->addRuntimeFields($runtime_fields);

        return $this;
    }
}
