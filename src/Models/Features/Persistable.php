<?php

namespace Gchaumont\Models\Features;

use App\Support\Elasticsearch\Elasticsearch; // TODO Replace with Builder

trait Persistable
{
    // INDEXABLE

    public function save(string|array $source = null): static
    {
        return $this->get_id() ? $this->upsert($source) : $this->insert();
    }

    public function insert(): static
    {
        $response = resolve(Elasticsearch::class)->index([
            'index' => $this->writableIndexName(),
            'body' => $this->serialise(),
        ]);

        $this->_id = (string) $response['_id'];

        return $this;
    }

    public function upsert(null|string|array $source = null): static
    {
        $response = resolve(Elasticsearch::class)->update([
            'index' => $this->writableIndexName(),
            'id' => $this->get_id(),
            'body' => array_filter([
                'doc_as_upsert' => true,
                'doc' => $this->serialise(),
                '_source' => $source,
            ]),
        ]);

        if ($source) {
            $this->addSerialisedData($response['get']['_source']);
        }

        return $this;
    }

    public function delete()
    {
        return resolve(Elasticsearch::class)->delete([
            'index' => $this->writableIndexName(),
            'id' => $this->get_id(),
        ]);
    }
}
