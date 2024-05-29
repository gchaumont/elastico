<?php

namespace Elastico\Models;

use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use Illuminate\Database\Eloquent\Model;
use App\Support\Elasticsearch\Mapping\ElasticIdentifier;

class Snapshot extends Model
{
    public $table = 'batzo.system.elasticsearch.snapshots';


    public readonly string $id;

    // #[Field(type: FieldType::date)]
    public \DateTime $timestamp;

    // #[Field(type: FieldType::integer)]
    public int $duration; // milliseconds

    // #[Field(type: FieldType::keyword)]
    public string $snapshot;

    // #[Field(type: FieldType::keyword)]
    public string $state;

    // #[Field(type: FieldType::keyword)]
    public string $version;

    // #[Field(type: FieldType::keyword)]
    public array $indices;

    public static function make(
        string $snapshot,
        \DateTime $timestamp,
        int $duration,
        string $state,
        string $version,
        array $indices,
    ): self {
        $static = new self();
        $static->id = $snapshot;
        $static->timestamp = $timestamp;
        $static->duration = $duration;
        $static->snapshot = $snapshot;
        $static->state = $state;
        $static->version = $version;
        $static->indices = $indices;

        return $static;
    }

    public static function searchableIndexName(): string
    {
        return static::INDEX_NAME;
    }

    public function writableIndexName(): string
    {
        return static::INDEX_NAME;
    }

    public function getId(): ?string
    {
        return $this->id ?? null;
    }
}
