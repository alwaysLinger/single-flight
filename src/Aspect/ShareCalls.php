<?php

namespace Al\SingleFlight\Aspect;

use Al\SingleFlight\Annotation\ShareCalls as Annotation;
use Al\SingleFlight\ShareCalls as Barrier;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect()
 */
class ShareCalls extends AbstractAspect
{
    public $annotations = [
        Annotation::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var Annotation $annotation */
        $annotation = $proceedingJoinPoint->getAnnotationMetadata()->method[Annotation::class];

        return Barrier::shareAndWait($annotation->key, fn() => $proceedingJoinPoint->process(), $annotation->timeout);
    }
}