<?php

use NHor\PcntlParallel\Messages\WorkerExceptionMessage;
use NHor\PcntlParallel\ParallelTasks;
use NHor\PcntlParallel\PersistenceWorker;
use NHor\PcntlParallel\PersistenceWorkersPool;
use NHor\PcntlParallel\SingleTaskWorker;

function generateRandomString($length = 10): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}


test('separate workers parallel execution')->expect(function () {
    $worker1 = (new SingleTaskWorker())->setCallback(fn () => 1)->run();
    $worker2 =  (new SingleTaskWorker())->setCallback(fn () => 2)->run();
    $worker3 =  (new SingleTaskWorker())->setCallback(fn () => false)->run();
    $worker4 =  (new SingleTaskWorker())->setCallback(fn () => null)->run();
    $worker5 =  (new SingleTaskWorker())->setCallback(function () {})->run();

    return [
        $worker1->waitOutput(),
        $worker2->waitOutput(),
        $worker3->waitOutput(),
        $worker4->waitOutput(),
        $worker5->waitOutput()
    ];
})->toEqual([
    1, 2, false, null, null
]);

test('parallel tasks execution')->expect(fn () => ParallelTasks::add([
   fn () => 1,
   fn () => 2,
])->run()->waitOutput())->toEqual([
    1, 2
]);

test('parallel tasks exception')->expect(fn () => (new SingleTaskWorker())->setCallback(
    fn () => 1 / 0
)->run()->waitOutput())->toBeInstanceOf(WorkerExceptionMessage::class);


test('parallel concurrency execution')->expect(function () {
    $results =  ParallelTasks::add([
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
    ])->runWithProcessLimitation(2);

    expect($results[1])->toEqual($results[0])
        ->and($results[2])->not->toEqual($results[1])
        ->and($results[2])->toEqual($results[3]);
});

test('parallel concurrency timeout exception')->expect(function () {
    $results =  ParallelTasks::add([
        fn () => time(),
        fn () => time(),
        function () {
            sleep(2);
            return 1;
        },
        fn () => time()
    ])
        ->setTimeout(1)
        ->runWithProcessLimitation(2, waitTimeout: 3_000_000);

    expect($results[1])
        ->toEqual($results[0])
        ->and($results[2])
        ->toBeInstanceOf(WorkerExceptionMessage::class)
        ->and($results[3])
        ->toEqual($results[0]);
});

test('persistence task worker')->expect(function () {
    $worker = (new PersistenceWorker())
        ->setOnReceiveCallback(fn ($data) => $data)
        ->run();

    $tasks = [1,2,3,4,5];
    foreach ($tasks as $task) {
        $worker->dispatch($task);
    }
    foreach ($tasks as $task) {
        $result[] = $worker->waitOutput(waitTimeout: 20000);
    }
    $worker->kill();
    expect(15)->toEqual(array_sum($result));
});

test('persistence workers pool')->expect(function () {
    //pool with 2 available workers
    $pool = PersistenceWorkersPool::create(2)->run(function (mixed $job) {
        sleep(1);
        return $job;
    });

    $time = time();

    $tasks = [1,2,3,4,5];
    foreach ($tasks as $task) {
        $pool->dispatch($task, 2_000_000);
    }
    $pool->wait();
    $result = $pool->pullWorkersOutput();

    expect(3)
        ->toEqual(time() - $time)
        ->and(array_sum($result))
        ->toEqual(15);

    $pool->destroy();
});

test('wait specific task')->expect(function () {
    $parallelTasks = ParallelTasks::add([
        fn () => 'task1',
        fn () => 'task2',
        fn () => 'task3',

    ])->run();

    [, $task2Worker] = $parallelTasks->getTaskWorkers();
    expect('task2')->toEqual($task2Worker->waitOutput());
});

test('parallel tasks test buffer size')->expect(function () {
    $message1 = generateRandomString(1000);
    $message2 = generateRandomString(2000);
    $message3 = generateRandomString(3000);

    $parallelTasks = ParallelTasks::add([
        fn () => $message1,
        fn () => $message2,
        fn () => $message3,
    ])->run();


    expect([$message1, $message2, $message3])->toEqual($parallelTasks->waitOutput());
});

test('persistence workers test buffer size')->expect(function () {
    //pool with 2 available workers
    $pool = PersistenceWorkersPool::create(2)->run(function (mixed $job) {
        return $job;
    });

    $tasks = [generateRandomString(10000), generateRandomString(20000), generateRandomString(30000)];
    foreach ($tasks as $task) {
        $pool->dispatch($task, 4_000_000);
    }
    $pool->wait();
    $result = $pool->pullWorkersOutput();

    expect($tasks)->toEqual($result);

    $pool->destroy();
});


test('test results of workers pool')->expect(function () {
    //pool with 5 available workers
    $pool = PersistenceWorkersPool::create(5)->run(function (mixed $job) {
        sleep(1);
        return $job;
    });

    $tasks = [1,2,3,4,5,6,7,8,9];
    foreach ($tasks as $task) {
        $pool->dispatch($task, waitAvailableWorkerTimeout: 2_000_000); //if the wait is more than 2 seconds, an exception will be thrown
    }

    //retrieve the result available at the moment
    $result = $pool->pullWorkersOutput(); //[1,2,3,4,5]
    expect(15)->toEqual(array_sum($result));
    $pool->wait();
    $result = $pool->pullWorkersOutput(); //[6,7,8,9]
    expect(30)->toEqual(array_sum($result));
    $pool->destroy();
});

test('common execution callback')->expect(function () {

    $parallelTasks = ParallelTasks::add([
        function () {
            global $value;
            return 1 + $value;
        },
        function () {
            global $value;
            return 2 + $value;
        },
        function () {
            global $value;
            return 3 + $value;
        },
    ])
        ->setCommonBeforeExecutionCallback(function () {
            global $value;
            $value = 1;
        })
        ->run();

    $res = $parallelTasks->waitOutput();

    expect(9)->toEqual(array_sum($res));
});

test('workers pool common execution callback')->expect(function () {
    $pool = PersistenceWorkersPool::create(5)
        ->setCommonBeforeExecutionCallback(function () {
            global $value;
            $value = 1;
        })
        ->run(function (mixed $job) {
            global $value;
            return $job + $value;
        });

    $tasks = [1,2,3,4,5,6,7,8,9];
    foreach ($tasks as $task) {
        $pool->dispatch($task, waitAvailableWorkerTimeout: 2_000_000);
    }

    $result = $pool->pullWorkersOutput();
    expect(20)->toEqual(array_sum($result));
    $pool->wait();
    $result = $pool->pullWorkersOutput();
    expect(34)->toEqual(array_sum($result));
    $pool->destroy();
});
