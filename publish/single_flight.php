<?php

declare(strict_types=1);

return [
    'exception_handler' => [\Al\SingleFlight\ExceptionHandler::class, 'handle'],

    // someClass::someMethod => [SomeClass::class, 'someKeyMethod']
    'keyBys' => [
        
    ]
];
