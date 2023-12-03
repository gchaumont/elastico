<?php

namespace Elastico\Index;

use Elastico\Mapping\Field;



class Mappings
{
    public function __construct(
        public array $properties = [],
        public array $dynamic_templates = [],
        public null|bool|string $dynamic = 'strict',
        public null|array $_source = null,
        // // Metadata Fields 
        // public array $_meta = [],
        // public array $_routing = [],
        // public array $_source = [],
        // public array $_index = [],
    ) {
    }

    public function properties(Field ...$properties): static
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'properties' => collect($this->properties)
                ->keyBy(fn (Field $prop) => $prop->getName())
                ->toArray(),
            'dynamic_templates' => $this->dynamic_templates,
            'dynamic' => $this->dynamic,
            // '_meta' => $this->_meta,
            // '_routing' => $this->_routing,
            '_source' => $this->_source,
            // '_index' => $this->_index,
        ]);
    }
}
