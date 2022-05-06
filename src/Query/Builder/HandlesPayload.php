<?php

namespace Gchaumont\Query\Builder;

trait HandlesPayload
{
    private array $payload = [];

    public function dd(): never
    {
        if (request()->expectsJson()) {
            response($this->buildPayload())->send();
        }
        dd($this->buildPayload());
    }

    public function buildPayload(): array
    {
        $this->payload['index'] = $this->index;

        if (!is_null($this->query)) {
            $this->payload['body']['query'] = $this->query->compile();
        }

        if (!is_null($this->take)) {
            $this->payload['body']['size'] = $this->take;
        }

        if (!empty($this->source)) {
            $this->payload['body']['_source']['includes'] = $this->source;
        }

        if (!empty($this->fields)) {
            $this->payload['body']['stored_fields'] = $this->fields;
        }

        if ($this->collapse) {
            $this->payload['body']['collapse']['field'] = $this->collapse;
        }

        if (!empty($this->sort)) {
            foreach ($this->sort as $sort) {
                $this->payload['body']['sort'][] = [
                    $sort['by'] => array_filter([
                        'order' => $sort['order'],
                        'missing' => $sort['missing'] ?? null,
                        'mode' => $sort['mode'],
                        'nested' => $sort['nested'],
                    ]),
                ];
            }
        }

        if ($this->skip) {
            $this->payload['body']['from'] = $this->skip;
        }

        if (!empty($this->post_filter)) {
            $this->payload['body']['post_filter'] = $this->post_filter->compile();
        }

        if ($this->getAggregations()->isNotEmpty()) {
            $this->payload['body']['aggs'] = $this->getAggregations()->map(fn ($agg) => $agg->compile())->all();
        }

        if (!empty($this->filterPath)) {
            $this->payload['filter_path'] = $this->filterPath;
        }

        if ($this->profile) {
            $this->payload['body']['profile'] = true;
        }

        $this->buildSuggests();

        $this->buildRanks();

        return $this->payload;
    }

    private function buildSuggests(): void
    {
        if (!empty($this->suggest)) {
            foreach ($this->suggest as $suggestion) {
                $this->payload['body']['suggest'] = [
                    $suggestion['name'] => [
                        'text' => $suggestion['text'],
                        $suggestion['type'] => array_filter([
                            'field' => $suggestion['field'],
                            'size' => $suggestion['size'],
                            'sort' => $suggestion['sort'],
                            'suggest_mode' => $suggestion['mode'],
                            'min_doc_freq' => $suggestion['min_doc_freq'],
                        ]),
                    ],
                ];
            }
        }
    }

    private function buildRanks(): void
    {
        foreach ($this->ranks as $rank) {
            $this->payload['body']['query']['bool']['should'][] = [
                'rank_feature' => [
                    'field' => $rank['field'],
                    'boost' => $rank['boost'],
                ],
            ];
        }
    }
}
