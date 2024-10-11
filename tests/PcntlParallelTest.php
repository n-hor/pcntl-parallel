<?php

use NHor\PcntlParallel\Messages\WorkerExceptionMessage;
use NHor\PcntlParallel\ParallelTasks;
use NHor\PcntlParallel\PersistenceWorkersPool;
use NHor\PcntlParallel\SingleTaskWorker;

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
            return time();
        },
        fn () => time(),
    ])
        ->setTimeout(1)
        ->runWithProcessLimitation(2);

    expect($results[1])
        ->toEqual($results[0])
        ->and($results[2])
        ->toBeInstanceOf(WorkerExceptionMessage::class)
        ->and($results[3])
        ->toEqual($results[0]);
});

test('persistence workers pool')->expect(function () {
    //pool with 2 available workers
    $pool = PersistenceWorkersPool::create(2)->run(function (mixed $job) {
        sleep(1);
        return $job;
    });

    $time = time();

    $pool->dispatch(1);
    $pool->dispatch(2);
    //1 sec wait
    $pool->dispatch(3);
    $pool->dispatch(4);
    //1 sec wait
    $pool->dispatch(5);

    //1 sec wait
    $pool->wait();
    $result = $pool->pullWorkersOutput();

    expect(3)
        ->toEqual(time() - $time)
        ->and(array_sum($result))
        ->toEqual(15);

    $pool->destroy();
});
