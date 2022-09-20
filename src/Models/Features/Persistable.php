<?php

namespace Elastico\Models\Features;

use Http\Promise\Promise;

// TODO Replace with Builder

trait Persistable
{
    // INDEXABLE

    public function save(string|array $source = null, bool|string $refresh = null): static
    {
        return $this->getKey() ? $this->upsert($source, $refresh) : $this->insert($refresh);
    }

    public function insert(null|bool|string $refresh = null): static
    {
        $response = static::getConnection()->performQuery(method: 'index', payload: [
            'index' => $this->writableIndexName(),
            'refresh' => $refresh,
            'body' => $this->serialise(),
        ]);
        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }

        return $this->set_id(
            (string) $response['_id']
        );
    }

    public function upsert(null|string|array $source = null, null|bool|string $refresh = null): static
    {
        $response = static::getConnection()->performQuery('update', [
            'index' => $this->writableIndexName(),
            'id' => $this->getKey(),
            'refresh' => $refresh,
            'body' => array_filter([
                'doc_as_upsert' => true,
                'doc' => $this->serialise(),
                '_source' => $source,
            ]),
        ]);

        if ($response instanceof Promise) {
            $response = $response->wait()->asArray();
        }

        if ($source) {
            $this->addSerialisedData($response['get']['_source']);
        }

        return $this;
    }

    public function delete(null|bool|string $refresh = null)
    {
        return static::getConnection()->performQuery('delete', [
            'index' => $this->writableIndexName(),
            'refresh' => $refresh,
            'id' => $this->getKey(),
        ]);
    }
}
