## DESCRIPTION
```
Basically this package provides a coroutine barrier annotation to share calls result between coroutines for hyperf framework
```

## INSTALLATION
```
composer require yylh/single-flight
php bin/hyperf vendor:publish yylh/single-flight
```

## EXAMPLE
```php
# SomeService.php
/**
 * @Barrier(key="some_barrier_key")
 */
public function test()
{
    var_dump('only do once');
    sleep(2);
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