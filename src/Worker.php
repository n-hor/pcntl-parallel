<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use NHor\PcntlParallel\Contracts\Packer;
use NHor\PcntlParallel\Contracts\Serializer;
use NHor\PcntlParallel\Exceptions\WorkerException;
use NHor\PcntlParallel\Exceptions\WorkerTimeoutException;
use NHor\PcntlParallel\Messages\WorkerExceptionMessage;
use NHor\PcntlParallel\Packers\DefaultPacker;
use NHor\PcntlParallel\Serializers\NativeSerializer;
use Socket;

abstract class Worker
{
    protected WorkerStatus $status = WorkerStatus::Idle;

    protected int $pid;

    protected bool $isChildProcess;

    protected Channel $channel;

    protected int $timeout = 0;

    protected Packer $packer;

    protected Serializer $serializer;

    protected int $channelBufferSize = 1024;

    public function run(): static
    {
        [$parentSocket, $childSocket] = $this->createCommunicationSockets();

        $this->pid = pcntl_fork();

        if ($this->pid === -1) {
            throw new WorkerException("Couldn't fork.");
        }

        $this->isChildProcess = $this->pid === 0;
        $this->status = WorkerStatus::Active;

        if ($this->isChildProcess) {
            $this->channel = $this->createChannel($childSocket);
            $this->listenForSignals();
            $this->process();
            exit;
        }

        $this->channel = $this->createChannel($parentSocket);

        return $this;
    }

    public function getOutput(): mixed
    {
        $this->checkWorkerStatus();

        return $this->channel->read()->current();
    }

    public function waitOutput(int $sleepTimeout = 1000)
    {
        do {
            $output = $this->getOutput();

            $noContent = $output === Channel::NO_CONTENT;

            if ($sleepTimeout > 0 && $noContent) {
                usleep($sleepTimeout);
            }
        } while ($noContent);

        return $output;
    }

    public function getWorkerStatus(): WorkerStatus
    {
        $this->checkWorkerStatus();

        return $this->status;
    }

    public function kill(): void
    {
        if (! extension_loaded('posix')) {
            exit;
        }

        if (! $this->isChildProcess) {
            posix_kill($this->pid, SIGKILL);
        } else {
            posix_kill(getmypid(), SIGKILL);
        }
    }

    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
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

    public function setChannelBufferSize(int $channelBufferSize): Worker
    {
        $this->channelBufferSize = $channelBufferSize;
        return $this;
    }

    abstract protected function process(): void;

    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        if ($this->timeout > 0) {
            pcntl_alarm($this->timeout);
        }

        pcntl_signal(SIGQUIT, fn () => $this->kill());
        pcntl_signal(SIGTERM, fn () => $this->kill());
        pcntl_signal(SIGINT, fn () => $this->kill());
        pcntl_signal(SIGALRM, function () {
            $this->channel->send(
                new WorkerExceptionMessage(new WorkerTimeoutException($this->timeout))
            );
            $this->kill();
        });
    }

    protected function createCommunicationSockets(): array
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
        return $sockets;
    }

    protected function checkWorkerStatus(): void
    {
        if ($this->status === WorkerStatus::Killed) {
            return;
        }

        $status = pcntl_waitpid($this->pid, $status, WNOHANG | WUNTRACED);

        if ($status === $this->pid) {
            $this->status = WorkerStatus::Killed;
        }
    }

    protected function createChannel(Socket $socket): Channel
    {
        return new Channel(
            socket: $socket,
            bufferSize: $this->channelBufferSize,
            serializer: $this->serializer ?? new NativeSerializer(),
            packer: $this->packer ?? new DefaultPacker($this->channelBufferSize)
        );
    }
}
