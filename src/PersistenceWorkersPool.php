<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use Closure;
use NHor\PcntlParallel\Contracts\Packer;
use NHor\PcntlParallel\Contracts\Serializer;
use NHor\PcntlParallel\Exceptions\PoolAlreadyCreatedException;
use NHor\PcntlParallel\Exceptions\TimeoutException;
use NHor\PcntlParallel\Exceptions\WorkerException;

class PersistenceWorkersPool
{
    protected int $sleepTimeout = 1_000;

    protected array $workersOutput = [];

    /**
     * @var array<PersistenceWorker>
     */
    protected array $inProcess = [];

    /**
     * @var array<PersistenceWorker>
     */
    protected array $idle = [];

    protected int $workersCount;

    protected Packer $packer;

    protected Serializer $serializer;

    protected ?Closure $commonBeforeExecutionCallback = null;

    public function __construct(int $workersCount)
    {
        $this->workersCount = $workersCount;
    }

    public static function create(int $workersCount): static
    {
        return new static($workersCount);
    }

    /**
     * @throws PoolAlreadyCreatedException
     * @throws WorkerException
     */
    public function run(Closure $onReceiveCallback): static
    {
        if (count($this->getWorkers()) > 0) {
            throw new PoolAlreadyCreatedException();
        }

        for ($workerNum = 0; $workerNum < $this->workersCount; ++$workerNum) {
            $worker = (new PersistenceWorker())
                ->setSleepTimeout($this->sleepTimeout)
                ->setBeforeExecutionCallback($this->commonBeforeExecutionCallback)
                ->setOnReceiveCallback($onReceiveCallback);

            if (isset($this->packer)) {
                $worker->setPacker($this->packer);
            }

            if (isset($this->serializer)) {
                $worker->setSerializer($this->serializer);
            }

            $this->idle[] = $worker->run();
        }

        return $this;
    }

    /**
     * @throws TimeoutException|WorkerException
     */
    public function dispatch(mixed $content, int $waitAvailableWorkerTimeout = 1_000_000): static
    {
        $startWaitTime = microtime(true);
        $waitAvailableWorkerTimeout /= 1_000_000;

        while (true) {
            if ($waitAvailableWorkerTimeout > 0 && ((microtime(true) - $startWaitTime) > $waitAvailableWorkerTimeout)) {
                throw new TimeoutException($waitAvailableWorkerTimeout);
            }

            $hasAvailableWorker = $this->hasAvailableWorker();

            if ($hasAvailableWorker) {
                $worker = array_shift($this->idle);
                break;
            }

            $this->sleep();
        }

        $this->inProcess[] = $worker->dispatch($content);

        return $this;
    }

    public function destroy(): void
    {
        foreach ($this->getWorkers() as $worker) {
            $worker->kill();
        }
        $this->inProcess = [];
        $this->idle = [];
    }

    public function pullWorkersOutput(): array
    {
        $this->checkIfAnyWorkerCompleteTask();

        $output = $this->workersOutput;
        $this->workersOutput = [];

        return $output;
    }

    /**
     * @return array<PersistenceWorker>
     */
    public function getWorkers(): array
    {
        return array_merge(
            $this->inProcess,
            $this->idle
        );
    }

    public function hasAvailableWorker(): bool
    {
        $this->checkIfAnyWorkerCompleteTask();

        $killedWorkersCount = count(array_filter(
            $this->getWorkers(),
            fn (PersistenceWorker $worker) => $worker->getWorkerStatus() === WorkerStatus::Killed
        ));

        if ($killedWorkersCount > 0 && $killedWorkersCount === $this->workersCount) {
            throw new WorkerException('All workers killed');
        }

        return count($this->idle) > 0;
    }

    public function hasWorkerInProcess(): bool
    {
        $this->checkIfAnyWorkerCompleteTask();

        return count($this->inProcess) > 0;
    }

    public function wait(): void
    {
        while (true) {
            if ($this->hasWorkerInProcess()) {
                $this->sleep();
            } else {
                break;
            }
        }
    }

    public function setPacker(Packer $packer): static
    {
        $this->packer = $packer;
        return $this;
    }

    public function setSerializer(Serializer $serializer): static
    {
        $this->serializer = $serializer;
        return $this;
    }

    public function setSleepTimeout(int $sleepTimeout): static
    {
        $this->sleepTimeout = $sleepTimeout;
        return $this;
    }

    public function setCommonBeforeExecutionCallback(?Closure $commonBeforeExecutionCallback): static
    {
        $this->commonBeforeExecutionCallback = $commonBeforeExecutionCallback;
        return $this;
    }

    protected function checkIfAnyWorkerCompleteTask(): void
    {
        foreach ($this->inProcess as $worker) {
            $output = $worker->getOutput();

            if ($output === Channel::NO_CONTENT) {
                continue;
            }
            $this->idle[] = $worker;
            $this->workersOutput[] = $output;
            $this->inProcess = array_filter($this->inProcess, fn (PersistenceWorker $workerInProcess) => $workerInProcess !== $worker);
        }
    }

    protected function sleep(): void
    {
        if ($this->sleepTimeout > 0) {
            usleep($this->sleepTimeout);
        }
    }
}
