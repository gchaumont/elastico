<?php

namespace Elastico\Mapping;

enum FieldType: string
{
    case binary = 'binary';
    case boolean = 'boolean';
    case byte = 'byte';
    case date = 'date';
    case double = 'double';
    case float = 'float';
    case half_float = 'half_float';
    case integer = 'integer';
    case keyword = 'keyword';
    case long = 'long';
    case numeric = 'numeric';
    case scaled_float = 'scaled_float';
    case short = 'short';
    case unsigned_long = 'unsigned_long';
    case flattened = 'flattened';
    case join = 'join';
    case nested = 'nested';
    case object = 'object';
    case dense_vector = 'dense_vector';
    case rank_feature = 'rank_feature';
    case rank_features = 'rank_features';
    case date_range = 'date_range';
    case double_range = 'double_range';
    case float_range = 'float_range';
    case integer_range = 'integer_range';
    case ip_range = 'ip_range';
    case ip = 'ip';
    case long_range = 'long_range';
    case completion = 'completion';
    case search_as_you_type = 'search_as_you_type';
    case text = 'text';
    case token_count = 'token_count';
    case geo_point = 'geo_point';
    case geo_shape = 'geo_shape';
    case point = 'point';
    case shape = 'shape';
    case composite = 'composite'; // Runtime field

    public function isTextSearchable(): bool
    {
        return match ($this) {
            static::keyword,
            static::text => true,
            default => false
        };
    }
}
