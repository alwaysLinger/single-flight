<?php

namespace Al\SingleFlight\Exception;

use Al\SingleFlight\Annotation\SingleFlight;

class NoResultException extends SingleFlightException
{
    public function __construct(string $barrierKey, \Throwable $previous = null)
    {
        $message = sprintf(
            'No shared result found in current coroutine context by key %s, and this most likely because of an execution timeout',
            $barrierKey
        );

        parent::__construct($message, 0, $previous);
    }
}