<?php

namespace Al\SingleFlight\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Utils\Arr;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Barrier extends AbstractAnnotation
{
    public const Key_Template = 'barrier_key:%s';

    /**
     * @var string
     */
    public string $key = '';

    /**
     * this keyBy callable array must return a barrier key which is string type
     * @var array
     */
    public array $keyBy = [];

    /**
     * wheter supress the cut-in method's throwable
     * @var bool
     */
    public bool $supressThrowable = false;

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
        $key = Arr::get($value, 'key', false);
        $keyBy = Arr::get($value, 'keyBy', []);
        if (!$key && empty($keyBy)) {
            throw new SingleFlightException('Barrier annotation needs a specified barrier key to share calls between coroutines');
        }
        return $value;
    }

    public function getBarrierKey(): string
    {
        return sprintf(
            self::Key_Template,
            value(fn($key) => $key ?: $this->key = call_user_func($this->keyBy), $this->key)
        );
    }
}