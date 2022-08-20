<?php

namespace Elastico\Models\Features;

// TODO Replace with Builder

trait Persistable
{
    // INDEXABLE

    public function save(string|array $source = null, bool|string $refresh = null): static
    {
        return $this->get_id() ? $this->upsert($source, $refresh) : $this->insert($refresh);
    }

    public function insert(null|bool|string $refresh = null): static
    {
        $this->_id = (string) static::getConnection()->performQuery(method: 'index', payload: [
            'index' => $this->writableIndexName(),
            'refresh' => $refresh,
            'body' => $this->serialise(),
        ])['_id'];

        // $this->_id = (string) static::getConnection()->index([
        //     'index' => $this->writableIndexName(),
        //     'body' => $this->serialise(),
        // ])->response()['_id'];

        return $this;
    }

    public function upsert(null|string|array $source = null, null|bool|string $refresh = null): static
    {
        $response = static::getConnection()->performQuery('update', [
            'index' => $this->writableIndexName(),
            'id' => $this->get_id(),
            'refresh' => $refresh,
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

    public function delete(null|bool|string $refresh = null)
    {
        return static::getConnection()->performQuery('delete', [
            'index' => $this->writableIndexName(),
            'refresh' => $refresh,
            'id' => $this->get_id(),
        ]);
    }
}
