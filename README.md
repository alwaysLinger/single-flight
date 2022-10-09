## DESCRIPTION
```
Basically this package provides a coroutine barrier annotation to share calls result between coroutines for hyperf framework
```

## INSTALLATION
```
composer require yylh/single-flight
php bin/hyperf.php vendor:publish yylh/single-flight
```

## EXAMPLE
```php
# SomeService.php
/**
 * @Barrier(key="some_barrier_key")
 */
public function foo()
{
    // only one coroutine can execute this method, others just wait for the shared result
    var_dump('only one goroutine can arrive here'); 
    sleep(2);
    return 'some result';
}

/**
 * @SingleFlight(key="another_barrier_key",supressThrowable=true,timeout=1)
 */
public function bar()
{
    var_dump('all goroutine will execute before yield');
    sleep(2); // other coroutines got yield here if there are any and just wait for the shared result
    var_dump('only one goroutine can arrive here');
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