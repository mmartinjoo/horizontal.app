<?php

namespace App\Exceptions\ElasticMemgraphService;

use Exception;

class NoAvailableInstances extends Exception
{
    public function __construct($message = "", $code = 0)
    {
        parent::__construct($message, $code);
    }

    public static function wrap(Exception $ex): self
    {
        return new static($ex->getMessage(), $ex->getCode(), $ex);
    }
}
