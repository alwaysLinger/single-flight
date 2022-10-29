<?php

namespace Al\SingleFlight\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Utils\Arr;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ShareCalls extends AbstractAnnotation
{
    public $key;

    /**
     * @var float
     */
    public $timeout;

    public function __construct(...$value)
    {
        $value = $this->formatParams($value);

        foreach ($value as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    protected function formatParams($value): array
    {
        $value = $value[0];
        $key = value(
            fn($key) => is_callable($key, true) ? call_user_func($key) : $key,
            tap(
                Arr::get($value, 'key'),
                fn($key) => throw_unless($key, \RuntimeException::class, 'ShareCalls annotation requires a specific barrier key')
            )
        );
        $timeout = Arr::get($value, 'timeout', 10.0);

        return compact('key', 'timeout');
    }
}