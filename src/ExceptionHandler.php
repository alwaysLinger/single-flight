<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Exception\NoResultException;
use Al\SingleFlight\Exception\TimeoutException;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;

class ExceptionHandler
{
    public static function handle(\Throwable $th)
    {
        ApplicationContext::getContainer()->get(StdoutLoggerInterface::class)->error('single-flight error:' . $th->getTraceAsString());
    }

    public static function noResultException(string $barrierKey): NoResultException
    {
        return new NoResultException($barrierKey);
    }

    public static function timeoutException(string $barrierKey)
    {
        return new TimeoutException($barrierKey);
    }
}