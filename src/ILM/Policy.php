<?php

namespace Elastico\ILM;

use Elastico\ILM\Phases\HotPhase;
use Elastico\ILM\Phases\ColdPhase;
use Elastico\ILM\Phases\WarmPhase;
use Elastico\ILM\Phases\DeletePhase;
use Elastico\ILM\Phases\FrozenPhase;


/**
 * Keeps the Client and performs the queries
 * Takes care of monitoring all queries.
 */
class Policy
{
    public string $id;

    public ?HotPhase $hot_phase = null;
    public ?WarmPhase $warm_phase = null;
    public ?ColdPhase $cold_phase = null;
    public ?FrozenPhase $frozen_phase = null;
    public ?DeletePhase $delete_phase = null;


    public function getName(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'policy' => [
                'phases' => array_filter([
                    'hot' => $this->hot_phase?->toArray(),
                    'warm' => $this->warm_phase?->toArray(),
                    'cold' => $this->cold_phase?->toArray(),
                    'frozen' => $this->frozen_phase?->toArray(),
                    'delete' => $this->delete_phase?->toArray(),
                ])
            ]
        ];
    }
}
