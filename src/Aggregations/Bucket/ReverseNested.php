<?php

namespace Gchaumont\Aggregations\Bucket;

use Gchaumont\Aggregations\Aggregation;

/**
 * ReverseNested Aggregation.
 */
class ReverseNested extends BucketAggregation
{
    public string $type = 'reverse_nested';

    public ?string $path = null;

    public function getPayload(): array
    {
        return $this->path ? [
            'path' => $this->path,
        ] : [];
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }
}
