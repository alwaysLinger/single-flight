<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Exception\ShareResultTimeoutException;
use Al\SingleFlight\Exception\WaitSharedResultTimeoutException;
use Closure;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Coroutine\Locker;
use Hyperf\Utils\WaitGroup;
use Swoole\Coroutine as SwooleCoroutine;
use Throwable;

class ShareCalls extends Locker
{
    public static ?Closure $beforeSuspend = null;

    protected static $results = [];
    protected static $sharedThrowables = [];
    protected static $waitThrowables = [];
    protected static $barriers = [];

    public static function shareAndWait(string $barrierKey, Closure $closure, float $timeout)
    {
        $responsibleCo = static::lock($barrierKey);

        if ($responsibleCo) {
            return value(
                function ($result) use ($barrierKey) {
                    if ($e = Arr::pull(static::$sharedThrowables, $barrierKey)) {
                        throw $e;
                    }
                    return $result;
                },
                static::shareResult($barrierKey, $closure, $timeout)
            );
        } else {
            return static::waitResult($barrierKey);
        }
    }

    public static function lock($key): bool
    {
        if (!self::has($key)) {
            self::add($key, 0);
            return true;
        }

        call_user_func(static::$beforeSuspend ?: static::getBeforeSuspend($key));
        self::add($key, Coroutine::id());
        SwooleCoroutine::suspend();
        return false;
    }

    protected static function shareResult(string $barrierKey, Closure $closure, float $timeout)
    {
        try {
            return tap(
                static::$results[$barrierKey] = wait(fn() => call_user_func($closure), $timeout),
                fn() => parent::unlock($barrierKey)
            );
        } catch (Throwable $th) {
            static::$sharedThrowables[$barrierKey] = new ShareResultTimeoutException('Share result failed', 0, $th);
            static::$waitThrowables[$barrierKey] = new WaitSharedResultTimeoutException('Wait result failed', 0, $th);
            parent::unlock($barrierKey);
        }
    }

    protected static function waitResult(string $barrierKey)
    {
        defer(fn() => static::$barriers[$barrierKey]->done());

        tap(Arr::get(static::$waitThrowables, $barrierKey), fn($th) => throw_if($th, $th));

        return static::$results[$barrierKey];
    }

    public static function clear($key): void
    {
        optional(Arr::get(static::$barriers, $key))->wait();
        unset(static::$results[$key], static::$waitThrowables[$key], static::$barriers[$key]);

        parent::clear($key);
    }

    protected static function getBeforeSuspend(string $barrierKey): Closure
    {
        return function () use ($barrierKey) {
            tap(
                Arr::get(static::$barriers, $barrierKey),
                function ($wg) use ($barrierKey) {
                    if ($wg) {
                        $wg->add();
                    } else {
                        $wg = static::$barriers[$barrierKey] = new WaitGroup();
                        $wg->add();
                    }
                }
            );
        };
    }
}