<?php

namespace Elastico\Mapping;

use Attribute;
use Elastico\Models\Model;
use Elastico\Mapping\Field;
use Elastico\Scripting\Script;
use Elastico\Models\DataAccessObject;
use Illuminate\Contracts\Support\Arrayable;

#[Attribute]
class RuntimeField implements Arrayable
{

    public function __construct(
        protected string|FieldType $type,
        protected string|Script $script,
        protected array $fields = [],
    ) {
        if (is_string($script)) {
            $this->script = new Script($script);
        }
        if (is_string($type)) {
            $this->type = FieldType::from($type);
        }
        collect($fields)
            ->ensure(Field::class)
            ->each(fn (Field $field) => $this->fields[$field->getName()] = $field);
    }

    public static function make(string|FieldType $type, string $name): static
    {
        if (!$type instanceof FieldType) {
            $type = FieldType::from($type);
        }

        return new static(type: $type, name: $name);
    }



    public function toArray(): array
    {
        $config['type'] = $this->type->name;
        $config['script'] = $this->script->compile();
        $config['fields'] = collect($this->fields)
            ->map(fn (Field $field) => $field->toArray())
            ->all();

        return array_filter($config);
    }
}
