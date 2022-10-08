## DESCRIPTION
```
Basically this package provides a SingleFlight annotation to share calls result between coroutines for hyperf framework
```

## INSTALLATION
```
composer require yylh/single-flight
```

## EXAMPLE
```php
# SomeService.php
/**
 * @SingleFlight(key="some_barrier_key")
 */
public function test()
{
    sleep(2);
    var_dump('only do once');
    return 'some result';
}

# test.php
public function handle()
{
    $count = 200;

    $wg = new WaitGroup($count);
    for ($i = 0; $i < $count; $i++) {
        go(function () use ($wg) {
            $ret = make(SomeService::class)->test();
            var_dump($ret);
            $wg->done();
        });
    }

    $wg->wait();
}
```

## TODOS
```
1、use stale cache in memory
```