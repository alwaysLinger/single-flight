<?php

namespace Al\SingleFlight;

use Al\SingleFlight\Exception\SingleFlightException;

class Carrier
{
    /**
     * the barrier key
     * @var string
     */
    public string $key;

    /**
     * whether supress the throwable
     * @var bool
     */
    public bool $supressThrowable;

    /**
     * the shared result
     * @var mixed
     */
    public $result = null;

    /**
     * @var callable
     */
    public $process;

    /**
     * maximum time that other coroutines can wait
     * @var int
     */
    public int $timeout;


    public function __construct(string $key, callable $process, bool $supressThrowable, int $timeout)
    {
        $this->key = $key;
        $this->supressThrowable = $supressThrowable;
        $this->process = $process;
        $this->timeout = $timeout;
    }

    public function setResult($result)
    {
        $this->result = $result;
    }

    public function getResult()
    {
        if (!$this->supressThrowable && $this->result instanceof \Throwable) {
            throw new SingleFlightException(sprintf("something unexcepted happend when try to share the key %s's result", $this->key), 0, $this->result);
        }
        return $this->result;
    }
}