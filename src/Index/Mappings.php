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
        public array $_routing = [],
        // public array $_meta = [],
        // public array $_index = [],
    ) {
    }

    public function properties(Field ...$properties): static
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }


    public function routing(array $routing): static
    {
        $this->_routing = $routing;

        return $this;
    }

    public function source(array $source): static
    {
        $this->_source = $source;

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
            '_routing' => $this->_routing,
            '_source' => $this->_source,
            // '_index' => $this->_index,
        ]);
    }
}
