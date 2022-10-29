## DESCRIPTION

```
Basically this package provides a coroutine barrier annotation to share calls result between coroutines for hyperf framework
```

## INSTALLATION

```
composer require yylh/single-flight
```

## EXAMPLE

```php
# SomeService.php
/**
 * invoke this proxied method will get one share exception
 * the rest will get wait result exception because of the timeout
 * 
 * @ShareCalls(key={SomeClass::class,"someMethod"},timeout=0.5)
 */
public function foo()
{
    // only one coroutine can execute this method,
    // others just wait for the shared result
    // this package only provides a barrier for IO operations
    var_dump('only one coroutine can reach here'); 
    sleep(2);
    return 'some result';
}

/**
 * one coroutine will process the method
 * others just wait for the result
 * 
 * @ShareCalls(key="some_barrier_key",timeout=2.0)
 */
public function bar() 
{
    var_dump('only one coroutine can reach here'); 
    sleep(1);
    return 'result';
}

# test.php
public function handle()
{
    $count = 200;

    $wg = new WaitGroup($count);
    for ($i = 0; $i < $count; $i++) {
        go(function () use ($wg) {
            $ret = make(SomeService::class)->foo();
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