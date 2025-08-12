<?php

namespace Elastico\Models;

use DateTime;
use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use Illuminate\Database\Eloquent\Model;

/**
 * Elastic model to track job completions.
 */
class Cluster extends Model
{
    public $table = 'batzo.system.elasticsearch.clusters';


    public readonly string $id;

    // #[Field(type: FieldType::date)]
    public DateTime $timestamp;

    // #[Field(type: FieldType::keyword)]
    public string $status;

    // #[Field(type: FieldType::integer)]
    public int $nodes;

    // #[Field(type: FieldType::integer)]
    public int $active_shards;

    // #[Field(type: FieldType::integer)]
    public int $relocating_shards;

    // #[Field(type: FieldType::integer)]
    public int $initialising_shards;

    // #[Field(type: FieldType::integer)]
    public int $unassigned_shards;

    // #[Field(type: FieldType::float)]
    public float $active_shards_percent_as_number;

    public static function make(
        DateTime $timestamp,
        string $status,
        int $nodes,
        int $active_shards,
        int $relocating_shards,
        int $initialising_shards,
        int $unassigned_shards,
        float $active_shards_percent_as_number,
    ): self {
        $static = new self();
        $static->id = '1';
        $static->timestamp = $timestamp;
        $static->status = $status;
        $static->nodes = $nodes;
        $static->active_shards = $active_shards;
        $static->relocating_shards = $relocating_shards;
        $static->initialising_shards = $initialising_shards;
        $static->unassigned_shards = $unassigned_shards;
        $static->active_shards_percent_as_number = $active_shards_percent_as_number;

        return $static;
    }
}
