<?php

namespace Elastico\Eloquent;

use Exception;
use Elastico\ILM\Policy;
use Elastico\Eloquent\Model;

/**
 * Reads and Writes Objects to a DataStream.
 */
abstract class DataStream extends Model
{
    public string|Policy $ilm_policy;

    public $primaryKey = '_id';

    public function getILMPolicy(): Policy
    {
        if (!isset($this->ilm_policy)) {
            throw new Exception('No policy set for this model');
        }

        if (is_string($this->ilm_policy)) {
            return new $this->ilm_policy;
        }

        return $this->ilm_policy;
    }
}
