<?php

namespace Al\SingleFlight;

use Closure;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Coroutine\Locker as HyperfLocker;
use Swoole\Coroutine as SwooleCoroutine;

class Locker extends HyperfLocker
{
    public static ?Closure $beforeSuspend = null;

    public static function lock($key): bool
    {
        if (!parent::has($key)) {
            parent::add($key, 0);
            return true;
        }

        self::add($key, Coroutine::id());
        call_user_func(static::$beforeSuspend);
        SwooleCoroutine::suspend();

        return false;
    }
}