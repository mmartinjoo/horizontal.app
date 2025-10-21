<?php

namespace App\Services\Integration\Communication\Exceptions;

use Exception;
use Throwable;

class FailedToLoadChannelsException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function wrap(Exception $ex): self
    {
        return new static($ex->getMessage(), $ex->getCode(), $ex);
    }
}
