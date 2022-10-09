<?php

namespace Al\SingleFlight\Coordinator;

use Al\SingleFlight\Barrier;
use Hyperf\Utils\Arr;
use Hyperf\Utils\ChannelPool;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

class Scheduler
{
    protected static $aquirePool = [];
    protected static $processCids = [];
    protected static $waitCids = [];
    protected static $waitBarrier = [];

    public static function aquire(string $barrierKey)
    {
        return tap(
            value(
                fn($chan) => $chan ?: static::$aquirePool[$barrierKey] = ChannelPool::getInstance()->get(),
                Arr::get(static::$aquirePool, $barrierKey, null)
            )->close(),
            function ($runAccess) use ($barrierKey) {
                if ($runAccess) {
                    Arr::set(static::$processCids, $barrierKey, Coroutine::getCid());
                } else {
                    static::addWaitToken($barrierKey);
                }
            }
        );
    }

    public static function release(string $barrierKey)
    {
        ChannelPool::getInstance()->release(Arr::get(static::$aquirePool, $barrierKey));

        Arr::forget(static::$aquirePool, $barrierKey);
        Arr::forget(static::$processCids, $barrierKey);
        Arr::forget(static::$waitCids, $barrierKey);
        Arr::forget(static::$waitBarrier, $barrierKey);
    }

    public static function wait(string $barrierKey, callable $getSharedResult)
    {
        Coroutine::yield();

        $getSharedResult();

        static::clearToken($barrierKey);
    }

    public static function notify(string $barrierKey)
    {
        foreach (Arr::get(static::$waitCids, $barrierKey, []) as $cid) {
            Coroutine::resume($cid);
        }
    }

    protected static function addWaitToken(string $barrierKey)
    {
        static::$waitCids[$barrierKey][] = Coroutine::getCid();
        static::getWaitBarrier($barrierKey)->add();
    }

    protected static function clearToken(string $barrierKey)
    {
        static::getWaitBarrier($barrierKey)->done();
    }

    protected static function getWaitBarrier(string $barrierKey)
    {
        return value(
            fn($waitGroup) => $waitGroup ?: tap(
                new WaitGroup(),
                fn($waitGroup) => Arr::set(static::$waitBarrier, $barrierKey, $waitGroup)
            ),
            Arr::get(static::$waitBarrier, $barrierKey, null)
        );
    }

    public static function waitAll(string $barrierKey)
    {
        return optional(
            Arr::get(static::$waitBarrier, $barrierKey, null),
            fn($waitGroup) => static::isProcessCo($barrierKey, Coroutine::getCid()) && $waitGroup->wait()
        );
    }

    protected static function isProcessCo(string $barrierKey, int $cid): bool
    {
        return Arr::get(static::$processCids, $barrierKey) == $cid;
    }

    public static function done(string $barrierKey, int $cid)
    {
        if (!static::isProcessCo($barrierKey, $cid)) {
            return;
        }

        Barrier::clearResult($barrierKey);
        static::release($barrierKey);
    }
}