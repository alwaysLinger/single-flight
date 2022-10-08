<?php

namespace Al\SingleFlight;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'single_flight',
                    'description' => 'The config for single-flight.',
                    'source' => __DIR__ . '/../publish/single_flight.php',
                    'destination' => BASE_PATH . '/config/autoload/single_flight.php',
                ],
            ],
        ];
    }
}