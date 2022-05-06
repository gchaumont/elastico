<?php

namespace Elastico\Aggregations\Pipeline;

use Elastico\Aggregations\Aggregation;

/**
 * BucketSelector Aggregation.
 */
class BucketSelector extends Aggregation
{
    public string $type = 'bucket_selector';

    public array $path;

    public string $script;

    public function getPayload(): array
    {
        return [
            'bucket_path' => $this->path,
            'script' => $this->script,
        ];
    }

    public function script(string $script): self
    {
        $this->script = $script;

        return $this;
    }

    public function path(array $path): self
    {
        $this->path = $path;

        return $this;
    }
}
