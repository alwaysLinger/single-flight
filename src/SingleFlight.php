<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Coordinator\CoordinatorManager;
use Hyperf\Context\Context;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Traits\Container;
use Swoole\Coroutine\WaitGroup;

class SingleFlight
{
    use Container;

    protected static $processCoroutine = [];

    public const AccessKeyTemplate = 'shared_access:%s';
    public const BarrierKeyTemplate = 'shared_call:%s';

    public static function getBarrierKey(string $key): string
    {
        return sprintf(self::BarrierKeyTemplate, $key);
    }

    public static function do(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        $wg = value(function ($key) {
            if (self::has(self::getBarrierKey($key))) {
                return self::get(self::getBarrierKey($key));
            } else {
                $wg = new WaitGroup();
                self::set(self::getBarrierKey($key), $wg);
                return $wg;
            }
        }, $key);

        self::shareCalls($key, $process, $supressThrowable, $timeout);
        self::waitResult($key, $timeout);

        if (self::$processCoroutine[self::getBarrierKey($key)] == Coroutine::id()) {
            $wg->wait();
            self::done($key);
        }
    }

    protected static function done(string $key)
    {
        CoordinatorManager::clear(self::getBarrierKey($key));
        CoordinatorManager::clear(self::getAccessKey($key));
        unset(self::$processCoroutine[self::getBarrierKey($key)]);
    }

    protected static function shareCalls(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        self::get(self::getBarrierKey($key))->add();
        if ($carrierIns = self::getCarrierInstance($key, $process, $supressThrowable, $timeout)) {
            self::$processCoroutine[self::getBarrierKey($key)] = Coroutine::id();
            try {
                $carrierIns->setResult(call_user_func($carrierIns->process));
            } finally {
                self::setResult($carrierIns->key, $carrierIns->getResult());
            }
        }
    }

    public static function getCarrierInstance(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        if (CoordinatorManager::until(self::getAccessKey($key))->resume()) {
            return new Carrier($key, $process, $supressThrowable, $timeout);
        }

        return false;
    }

    protected static function getAccessKey(string $key): string
    {
        return sprintf(self::AccessKeyTemplate, $key);
    }

    public static function waitResult(string $key, int $timeout)
    {
        CoordinatorManager::until(self::getBarrierKey($key))->yield($timeout);
        $result = Context::set(self::getBarrierKey($key), Context::get(self::getBarrierKey($key), null, self::$processCoroutine[self::getBarrierKey($key)]));
        self::get(self::getBarrierKey($key))->done();
        return $result;
    }

    public static function setResult(string $key, $result)
    {
        Context::set(self::getBarrierKey($key), $result);
        CoordinatorManager::until(self::getBarrierKey($key))->resume();
    }

    public static function getResult(string $key)
    {
        return Context::get(self::getBarrierKey($key));
    }
}