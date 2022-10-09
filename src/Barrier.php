<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Coordinator\Scheduler;
use Hyperf\Context\Context;
use Hyperf\Utils\Arr;
use Swoole\Coroutine;

class Barrier
{
    protected static $sharedResult = [];

    public static function do(string $barrierKey, callable $process)
    {
        if (Scheduler::aquire($barrierKey)) {
            static::shareResult($barrierKey, $process);
        } else {
            static::waitResult($barrierKey);
        }

        return static::wait($barrierKey);
    }

    protected static function shareResult(string $barrierKey, $process)
    {
        Arr::set(
            static::$sharedResult,
            $barrierKey,
            Context::set($barrierKey, value(fn($result) => is_callable($result) ? $result() : $result, $process))
        );

        static::notify($barrierKey);
    }

    protected static function notify(string $barrierKey)
    {
        Scheduler::notify($barrierKey);
    }

    protected static function waitResult(string $barrierKey)
    {
        Scheduler::wait($barrierKey, static::saveSharedResult($barrierKey));
    }

    protected static function saveSharedResult(string $barrierKey): callable
    {
        return fn() => Context::set($barrierKey, static::getSharedResult($barrierKey));
    }

    protected static function getSharedResult(string $barrierKey)
    {
        return Arr::get(static::$sharedResult, $barrierKey);
    }

    protected static function wait(string $barrierKey)
    {
        defer(fn() => Scheduler::done($barrierKey, Coroutine::getCid()));

        return Scheduler::waitAll($barrierKey);
    }

    public static function getResult(string $barrierKey)
    {
        return Context::get($barrierKey);
    }

    public static function clearResult(string $barrierKey)
    {
        Arr::forget(static::$sharedResult, $barrierKey);
    }
}