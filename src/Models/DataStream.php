<?php

namespace Elastico\Models;

use App\Support\Supervision\Dashboard\Fields\AdminField;
use DateTime;
use Elastico\Mapping\Field;
use Elastico\Mapping\FieldType;

/**
 * Reads and Writes Objects to a DataStream.
 */
abstract class DataStream extends Model // implements Serialisable, Recordable
{
    #[Field(type: FieldType::date, name: '@timestamp')]
    #[AdminField(name: 'Timestamp')]
    public DateTime $timestamp;
}
