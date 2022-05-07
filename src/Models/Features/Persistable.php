<?php

namespace Elastico\Models\Features;

// TODO Replace with Builder

trait Persistable
{
    // INDEXABLE

    public function save(string|array $source = null): static
    {
        return $this->get_id() ? $this->upsert($source) : $this->insert();
    }

    public function insert(): static
    {
        $this->_id = (string) static::getConnection()->performQuery(method: 'index', payload: [
            'index' => $this->writableIndexName(),
            'body' => $this->serialise(),
        ])['_id'];

        // $this->_id = (string) static::getConnection()->index([
        //     'index' => $this->writableIndexName(),
        //     'body' => $this->serialise(),
        // ])->response()['_id'];

        return $this;
    }

    public function upsert(null|string|array $source = null): static
    {
        $response = static::getConnection()->performQuery('update', [
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
        return static::getConnection()->performQuery('delete', [
            'index' => $this->writableIndexName(),
            'id' => $this->get_id(),
        ]);
    }
}
