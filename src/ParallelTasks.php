<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use Closure;
use NHor\PcntlParallel\Exceptions\TasksAlreadyInProcessException;
use NHor\PcntlParallel\Exceptions\WorkerException;

class ParallelTasks
{
    protected ?Closure $commonBeforeExecutionCallback = null;

    protected array $workers = [];

    protected int $timeout = 0;

    private function __construct(protected array $tasks = [])
    {
    }

    public static function add(array $tasks): static
    {
        return new static($tasks);
    }

    /**
     * @throws WorkerException
     * @throws TasksAlreadyInProcessException
     */
    public function run(): static
    {
        if ($this->workers) {
            throw new TasksAlreadyInProcessException();
        }

        $this->workers = array_map(fn ($task) => (new SingleTaskWorker())
            ->setTimeout($this->timeout)
            ->setCallback($this->getExecutableTask($task))
            ->run(), $this->tasks);

        return $this;
    }

    public function runWithProcessLimitation(int $processesLimit, int $sleepTimeout = 100, int $waitTimeout = 0): array
    {
        $chunks = array_chunk($this->tasks, $processesLimit);
        $result = [];

        foreach ($chunks as $tasks) {
            $tasksOutput = static::add($tasks)
                ->setTimeout($this->timeout)
                ->setCommonBeforeExecutionCallback($this->commonBeforeExecutionCallback)
                ->run()
                ->waitOutput($sleepTimeout, $waitTimeout);

            foreach ($tasksOutput as $value) {
                $result[] = $value;
            }
        }

        return $result;
    }

    public function getTaskWorkers(): array
    {
        return $this->workers;
    }

    public function waitOutput($sleepTimeout = 100, int $waitTimeout = 0): array
    {
        return array_map(fn (SingleTaskWorker $worker) => $worker->waitOutput($sleepTimeout, $waitTimeout), $this->workers);
    }

    public function setCommonBeforeExecutionCallback(?Closure $commonBeforeExecutionCallback): ParallelTasks
    {
        $this->commonBeforeExecutionCallback = $commonBeforeExecutionCallback;
        return $this;
    }

    public function setTimeout(int $timeout): ParallelTasks
    {
        $this->timeout = $timeout;
        return $this;
    }

    protected function getExecutableTask(Closure $task): Closure
    {
        return isset($this->commonBeforeExecutionCallback) ? function () use ($task) {
            ($this->commonBeforeExecutionCallback)();
            $task();
        } : $task;
    }
}
