<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Coordinator\CoordinatorManager;
use Hyperf\Context\Context;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\WaitGroup;

class SingleFlight
{
    protected static $waitGroupContainer = [];
    protected static $processCoroutine = [];
    protected static $sharedResult = [];

    public const AccessKeyTemplate = 'shared_access:%s';
    public const BarrierKeyTemplate = 'shared_call:%s';

    public static function getBarrierKey(string $key): string
    {
        return sprintf(static::BarrierKeyTemplate, $key);
    }

    public static function do(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        static::getWaitBarrier($key);

        static::shareCall($key, $process, $supressThrowable, $timeout);
        static::waitResult($key, $timeout);

        static::wait($key, $timeout);
    }

    protected static function shareCall(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        if ($access = static::canProcess($key)) {
            static::$processCoroutine[static::getBarrierKey($key)] = Coroutine::id();
            static::shareResult($key, call_user_func($process));
        } else {
            static::getWaitBarrier($key)->add();
        }

        return $access;
    }

    protected static function canProcess($key): bool
    {
        return CoordinatorManager::until(static::getAccessKey($key))->resume();
    }

    protected static function done(string $key)
    {
        CoordinatorManager::clear(static::getBarrierKey($key));
        CoordinatorManager::clear(static::getAccessKey($key));
        unset(static::$waitGroupContainer[static::getBarrierKey($key)]);
        unset(static::$processCoroutine[static::getBarrierKey($key)]);
        unset(static::$sharedResult[static::getBarrierKey($key)]);
    }

    protected static function getAccessKey(string $key): string
    {
        return sprintf(static::AccessKeyTemplate, $key);
    }

    protected static function waitResult(string $key, int $timeout)
    {
        $closed = CoordinatorManager::until(static::getBarrierKey($key))->yield($timeout);
        if (!$closed) {
            return Context::set(static::getBarrierKey($key), ExceptionHandler::timeoutException($key));
        }

        $result = Context::set(static::getBarrierKey($key), static::$sharedResult[static::getBarrierKey($key)]);
        if (static::$processCoroutine[static::getBarrierKey($key)] != Coroutine::id()) {
            static::getWaitBarrier($key)->done();
        }

        return $result;
    }

    protected static function shareResult(string $key, $result)
    {
        static::$sharedResult[static::getBarrierKey($key)] = $result;
        CoordinatorManager::until(static::getBarrierKey($key))->resume();
    }

    public static function getResult(string $key, $fallback = null)
    {
        return Context::get(static::getBarrierKey($key), $fallback);
    }

    protected static function getWaitBarrier(string $key): WaitGroup
    {
        return value(
            fn($key) => Arr::get(static::$waitGroupContainer, static::getBarrierKey($key), null) ?:
                static::$waitGroupContainer[static::getBarrierKey($key)] = new WaitGroup(),
            $key
        );
    }

    protected static function wait(string $key, int $timeout)
    {
        if (static::$processCoroutine[static::getBarrierKey($key)] == Coroutine::id()) {
            static::getWaitBarrier($key)->wait($timeout);
            static::done($key);
        }
    }
}