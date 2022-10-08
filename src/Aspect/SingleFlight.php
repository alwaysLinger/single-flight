<?php

namespace Al\SingleFlight\Aspect;

use Al\SingleFlight\Annotation\SingleFlight as Annotation;
use Al\SingleFlight\Exception\NoResultException;
use Al\SingleFlight\ExceptionHandler;
use Al\SingleFlight\SingleFlight as Barrier;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Utils\ApplicationContext;

/**
 * @Aspect()
 */
class SingleFlight extends AbstractAspect
{
    public $annotations = [
        Annotation::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var Annotation $annotation */
        $annotation = $proceedingJoinPoint->getAnnotationMetadata()->method[Annotation::class];
        $barrierKey = $annotation->getBarrierKey();

        Barrier::do($barrierKey, $this->makeProcess($proceedingJoinPoint), $annotation->supressThrowable, $annotation->timeout);

        return tap(
            Barrier::getResult($barrierKey, $this->noResultException($barrierKey)),
            fn($result) => throw_if(!$annotation->supressThrowable && $result instanceof \Throwable, $result)
        );
    }

    protected function noResultException(string $barrierKey): NoResultException
    {
        return ExceptionHandler::noResultException($barrierKey);
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