<?php

namespace Gchaumont\Aggregations\Options;

enum ExecutionHint : string
{
    case map = 'map';
    case global_ordinals = 'global_ordinals';
    case bytes_hash = 'bytes_hash';
}
