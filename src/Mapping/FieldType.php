<?php

namespace Elastico\Mapping;

enum FieldType
{
    case binary;
    case boolean;
    case byte;
    case date;
    case double;
    case float;
    case half_float;
    case integer;
    case keyword;
    case long;
    case numeric;
    case scaled_float;
    case short;
    case unsigned_long;
    case flattened;
    case join;
    case nested;
    case object;
    case dense_vector;
    case rank_feature;
    case rank_features;
    case date_range;
    case double_range;
    case float_range;
    case integer_range;
    case ip_range;
    case ip;
    case long_range;
    case completion;
    case search_as_you_type;
    case text;
    case token_count;
    case geo_point;
    case geo_shape;
    case point;
    case shape;
    public function isTextSearchable(): bool
    {
        return match ($this) {
            static::keyword ,
            static::text => true,
            default => false
        };
    }
}
