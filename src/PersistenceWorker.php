<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use Closure;
use NHor\PcntlParallel\Messages\WorkerExceptionMessage;
use Throwable;

class PersistenceWorker extends Worker
{
    protected int $sleepTimeout = 1000;

    protected Closure $onReceiveCallback;

    public function dispatch(mixed $content = null): static
    {
        $this->channel->send($content);
        return $this;
    }

    public function setOnReceiveCallback(Closure $onReceiveCallback): static
    {
        $this->onReceiveCallback = $onReceiveCallback;
        return $this;
    }

    public function setSleepTimeout(int $sleepTimeout): PersistenceWorker
    {
        $this->sleepTimeout = $sleepTimeout;
        return $this;
    }

    protected function process(): void
    {
        while (true) {
            $task = $this->channel->read()->current();

            if ($task !== Channel::NO_CONTENT) {
                try {
                    $result = ($this->onReceiveCallback)($task);
                    $this->channel->send($result);
                } catch (Throwable $throwable) {
                    $this->channel->send(new WorkerExceptionMessage($throwable));
                }
                continue;
            }
            if ($this->sleepTimeout > 0) {
                usleep($this->sleepTimeout);
            }
        }
    }
}
