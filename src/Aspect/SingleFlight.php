<?php

namespace Al\SingleFlight\Aspect;

use Al\SingleFlight\Annotation\SingleFlight as Annotation;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Aspect;
use Al\SingleFlight\SingleFlight as Barrier;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Utils\ApplicationContext;

/**
 * @Aspect
 */
class SingleFlight extends AbstractAspect
{
    public $annotations = [
        Annotation::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var Annotation $annotation */
        $annotation = $this->getBarrierAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);

        Barrier::do($annotation->getBarrierKey(), $this->makeProcess($proceedingJoinPoint), $annotation->supressThrowable, $annotation->timeout);
        return Context::get(Barrier::getBarrierKey($annotation->getBarrierKey()));
    }

    protected function getBarrierAnnotation(string $className, string $method)
    {
        return AnnotationCollector::getClassMethodAnnotation($className, $method)[Annotation::class] ?? null;
    }

    protected function makeProcess(ProceedingJoinPoint $proceedingJoinPoint): callable
    {
        return function () use ($proceedingJoinPoint) {
            try {
                $result = $proceedingJoinPoint->process();
            } catch (\Throwable $th) {
                $this->handleException($th);
            } finally {
                return isset($result) ? $result : $th;
            }
        };
    }

    protected function handleException(\Throwable $th)
    {
        ApplicationContext::getContainer()->get(StdoutLoggerInterface::class)->error($th->getMessage());
    }
}