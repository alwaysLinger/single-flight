<?php

namespace Al\SingleFlight\Exception;

use Al\SingleFlight\Annotation\SingleFlight;

class TimeoutException extends SingleFlightException
{
    public function __construct(string $barrierKey, \Throwable $previous = null)
    {
        $message = sprintf('No shared result found in current coroutine context within the maximum timeout by key %s', $barrierKey);

        parent::__construct($message, 0, $previous);
    }
}