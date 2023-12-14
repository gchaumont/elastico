<?php

namespace Elastico\Index;

use Elastico\Mapping\Field;
use Elastico\Index\Mappings;
use Elastico\Index\Settings;

class Config
{
    public function __construct(
        protected string $index,
        public array $aliases = [],
        public ?Mappings $mappings = null,
        public ?array $settings = null,
    ) {
    }

    public static function make(...$args): static
    {
        return new static(...$args);
    }

    public function settings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function aliases(Alias ...$aliases): static
    {
        $this->aliases = $aliases;

        return $this;
    }

    public function mappings(Field ...$mappings): static
    {
        $this->mappings = $mappings;

        return $this;
    }

    public function mapping(Mappings|callable $mappings): static
    {
        if ($mappings instanceof Mappings) {
            $this->mappings = $mappings;
        } else {
            $this->mappings = $mappings($this->getMappings());
        }

        return $this;
    }

    public function getMappings(): Mappings
    {
        return $this->mappings ??= new Mappings();
    }

    public function properties(Field ...$properties): static
    {
        $this->getMappings()->properties(...$properties);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'body' => array_filter([
                'mappings' => $this->mappings?->toArray(),
                'settings' => $this->settings,
            ]),
        ];
    }
}
