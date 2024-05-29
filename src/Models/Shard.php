<?php

namespace Elastico\Models;

use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;
use App\Support\Data\Formats\Format;
use Illuminate\Database\Eloquent\Model;
use App\Support\Data\Formats\Formatters\Bytes;
use App\Support\Data\Formats\Formatters\Roundable;
use App\Support\Elasticsearch\Mapping\ElasticIdentifier;

/**
 * Elastic model to track job completions.
 */
class Shard extends Model
{
    public $table = 'batzo.system.elasticsearch.shards';

    #[ElasticIdentifier]
    public readonly string $id;

    // #[Field(type: FieldType::date)]
    public \DateTime $timestamp;

    // #[Field(type: FieldType::object)]
    public Index $index;

    // #[Field(type: FieldType::keyword)]
    public string $shard;

    // #[Field(type: FieldType::keyword)]
    public string $prirep;

    // #[Field(type: FieldType::keyword)]
    public string $state;

    // #[Field(type: FieldType::long)]
    #[Format(Bytes::class)]
    public null|int $store;

    // #[Field(type: FieldType::long)]
    #[Format(Roundable::class)]
    public null|int $docs;

    // #[Field(type: FieldType::ip)]
    public null|string $ip;

    // #[Field(type: FieldType::object)]
    public null|Node $node;

    public static function make(
        string|Index $index,
        string $shard,
        \DateTime $timestamp,
        string $prirep,
        string $state,
        null|int $store,
        null|int $docs,
        null|string $ip,
        string|Node $node = null,
    ): self {
        $static = new self();
        $static->id = $shard . '-' . $index . '-' . $prirep;
        $static->timestamp = $timestamp;
        $static->index = $index;
        $static->shard = $shard;
        $static->prirep = $prirep;
        $static->store = $store;
        $static->state = $state;
        $static->docs = $docs;
        $static->ip = $ip;
        $static->node = $node;

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
