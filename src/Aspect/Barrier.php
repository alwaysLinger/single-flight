<?php

namespace Al\SingleFlight\Aspect;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\AbstractAspect;
use Al\SingleFlight\Annotation\Barrier as Annotation;
use Al\SingleFlight\Barrier as CoBarrier;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Utils\ApplicationContext;

/**
 * @Aspect()
 */
class Barrier extends AbstractAspect
{
    public $annotations = [
        Annotation::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var \Al\SingleFlight\Annotation\SingleFlight $annotation */
        $annotation = $proceedingJoinPoint->getAnnotationMetadata()->method[Annotation::class];
        $barrierKey = $annotation->getBarrierKey();

        CoBarrier::do($barrierKey, $this->makeProcess($proceedingJoinPoint));

        return tap(
            CoBarrier::getResult($barrierKey),
            fn($result) => throw_if(!$annotation->supressThrowable && $result instanceof \Throwable, $result)
        );
    }

    protected function makeProcess(ProceedingJoinPoint $proceedingJoinPoint): callable
    {
        return function () use ($proceedingJoinPoint) {
            try {
                $hasResult = false;
                $result = $proceedingJoinPoint->process();
                $hasResult = true;
            } catch (\Throwable $th) {
                $this->handleException($th);
            } finally {
                return $hasResult ? $result : $th;
            }
        };
    }

    protected function handleException(\Throwable $th)
    {
        $handler = ApplicationContext::getContainer()
            ->get(ConfigInterface::class)
            ->get('single_flight.exception_handler', [ExceptionHandler::class, 'handle']);

        return call_user_func($handler, $th);
    }
}