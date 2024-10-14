# Simple solution for running PHP code concurrently

This package for running code in parallel and creating a pool of processes to execute various tasks (like queue
workers).

Improve performance of your php app with this package!

## Installation

```bash
composer require n-hor/pcntl-parallel
```

## Requirements

- PHP >= 8.1
- ext-sockets
- ext-pcntl https://www.php.net/manual/en/intro.pcntl.php
- posix (optional)

## Usage

### SingleTaskWorker

Usage of `SingleTaskWorker`:

```php
use NHor\PcntlParallel\SingleTaskWorker;

$worker1 = (new SingleTaskWorker())
        ->setCallback(fn () => DB::insert(...))
        ->run();
        
$worker2 =  (new SingleTaskWorker())
        ->setCallback(fn () => DB::update(...))
        ->run();
        
// do something immediately
//...your code...
// or you can wait for the result of each worker
$resultWorker1 = $worker1->waitOutput(),
$resultWorker2 = $worker2->waitOutput(),
```

If an exception occurs in a parallel process, Worker returns `WorkerExceptionMessage` as output.
Example With timeout exception:

```php
use NHor\PcntlParallel\SingleTaskWorker;

//
$worker = (new SingleTaskWorker())
            //max execution time in seconds
            ->setTimeout(3)
            ->setCallback(fn () => YourLongTimeJob::run(...))
            ->run();

$result = $worker->waitOutput();

if($result instanceof WorkerExceptionMessage) {
  //check and handle errors from worker
}
```

You could use wrapper of `SingleTaskWorker` for running multiple single workers:

```php
use NHor\PcntlParallel\ParallelTasks;

$parallelTasks = ParallelTasks::add([
        function () {
            sleep(1);
            return time();
        },
        function () {
            sleep(1);
            return time();
        },
        function () {
            sleep(2);
            return time();
        },
        function () {
            sleep(2);
            return time();
        },
    ])
    // common callback that is executed after a fork in child processes,
    // such as reconnecting to services to avoid errors after a fork.
    ->setCommonBeforeExecutionCallback(function () {
          DB::reconnect();
          Redis::reconnect();
    })
    ->run();
    
// do something immediately
//...your code...

//You can wait for all tasks to complete and specify a sleep timeout in microseconds while waiting for output.
$result = $parallelTasks->waitOutput(sleepTimeout: 100, waitTimeout: 1_000_000);

$result[0] === $result[1]; //true
$result[2] === $result[3]; //true
```

You can wait for only a specific task to complete:

```php
use NHor\PcntlParallel\ParallelTasks;

$parallelTasks = ParallelTasks::add([
        fn() => 'task1',
        fn() => 'task2',
        fn() => 'task3',
  
    ])
    ->run();

//get tasks workers
[$task1, $task2, $task3] = $parallelTasks->getTaskWorkers();

$result = $task2->waitOutput(); //task2

//Or you can just check if there is a result without blocking the program
$task1Output = $task1->getOutput();

if ($task1Output !== Channel::NO_CONTENT) {
    //task done
}
```

Example of running with concurrency limitation:

```php
use NHor\PcntlParallel\ParallelTasks;
use NHor\PcntlParallel\Messages\WorkerExceptionMessage;

$result = ParallelTasks::add([
    fn () => time(),
    fn () => time(),
    function () {
        sleep(2);
        return time();
    },
    fn () => time(),
])
    //set max execution time in seconds for each worker
    ->setTimeout(1)
    //at the same time only 2 parallel process is running
    //this method wait when all process is complete.
    ->runWithProcessLimitation(2);

//WorkerExceptionMessage since third task is sleep 2 seconds but timeout is 1 second.
$result[2] instanceof WorkerExceptionMessage; //true
```

`PersistenceWorker` is a worker that is started once and runs in the background as a daemon.
It receives and executes tasks from the parent process.

Usage of `PersistenceWorker`:

```php
use NHor\PcntlParallel\PersistenceWorker;

$worker = (new PersistenceWorker())
       ->setOnReceiveCallback(function ($job) {
          return $job->handle();
       })
       ->run();

$jobs = [
         new Job($data),
         new Job($data),
         new Job($data)
      ];
        
foreach ($jobs as $job) {
    $worker->dispatch($job);
}

// do something immediately
//...your code...

// or you can wait for the result of each job
$result[] = $worker->waitOutput(waitTimeout: 2_000); //job1 result
$result[] = $worker->waitOutput(waitTimeout: 2_000); //job2 result

//kill worker
$worker->kill();
```
### PersistenceWorkersPool

`PersistenceWorkersPool` A pool of processes for sending tasks.
A free worker will be selected to perform the task.
If all workers are busy, it will wait until at least one is free.
You can specify a maximum wait time, in which case an exception will be thrown.

Usage of `PersistenceWorkersPool`:

```php
use NHor\PcntlParallel\PersistenceWorkersPool;

//pool with 5 available workers
$pool = PersistenceWorkersPool::create(5)
  //executed once when a worker is started
  ->setCommonBeforeExecutionCallback(function () {
          DB::reconnect();
          Redis::reconnect();
    })
    ->run(function (mixed $job) {
        sleep(1);
        return $job;
    });

$tasks = [1,2,3,4,5,6,7,8,9];

foreach ($tasks as $task) {
    //1,2,3,4,5 - will be sent immediately, but the next tasks will wait until some worker completes the task.
    $pool->dispatch($task, waitAvailableWorkerTimeout: 2_000_000); //if the wait is more than 2 seconds, an exception will be thrown
} 

//retrieve the result available at the moment
$result = $pool->pullWorkersOutput(); //[1,2,3,4,5]

//wait next results
$pool->wait();

$result = $pool->pullWorkersOutput(); //[6,7,8,9]
```

## Testing

```bash
composer test
```

## Credits

- [Nikolay Horobets](https://github.com/n-hor)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## PHP sandbox:

<a href="https://sandbox.ws">https://sandbox.ws</a>
