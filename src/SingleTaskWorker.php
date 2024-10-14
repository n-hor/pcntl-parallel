<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use Closure;
use NHor\PcntlParallel\Exceptions\WorkerException;
use NHor\PcntlParallel\Messages\WorkerExceptionMessage;
use Throwable;

class SingleTaskWorker extends Worker
{
    protected Closure $closure;

    public function run(): static
    {
        if (! isset($this->closure)) {
            throw new WorkerException('Task not defined.');
        }

        return parent::run();
    }

    public function setCallback(Closure $closure): static
    {
        $this->closure = $closure(...);
        return $this;
    }

    protected function process(): void
    {
        try {
            $result = ($this->closure)();
            $this->channel->send($result);
        } catch (Throwable $throwable) {
            $this->channel->send(new WorkerExceptionMessage($throwable));
        }
    }
}
