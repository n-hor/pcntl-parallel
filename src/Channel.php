<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel;

use Generator;
use NHor\PcntlParallel\Contracts\Serializer;
use NHor\PcntlParallel\Packers\DefaultPacker;
use Socket;

class Channel
{
    public const NO_CONTENT = 0x00;

    public Generator $reader;

    protected string $buffer = '';

    public function __construct(
        protected Socket $socket,
        protected int $bufferSize,
        protected Serializer $serializer,
        protected DefaultPacker $packer,
        protected int $socketSecondsTimeout = 1,
        protected int $socketMicroSecondsTimeout = 1_000_000,
    ) {
        $this->reader = $this->read();
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }

    public function read(): Generator
    {
        socket_set_nonblock($this->socket);

        while (true) {
            $output = socket_read($this->socket, $this->bufferSize);

            if ($output === false || $output === '') {
                yield self::NO_CONTENT;
                continue;
            }

            $messages = $this->packer->unpack($output);

            foreach ($messages as $message) {
                yield $this->serializer->unserialize($message);
            }
        }
    }

    public function send(mixed $content): void
    {
        $serialized = $this->serializer->serialize($content);
        $packedData = $this->packer->pack($serialized);

        foreach ($packedData as $chunk) {
            $this->write($chunk);
        }
    }

    public function close(): self
    {
        socket_close($this->socket);
        return $this;
    }

    protected function write(string $data): void
    {
        socket_set_nonblock($this->socket);

        while ($data !== '') {
            if (! $this->socketSelect(false)) {
                break;
            }

            $length = strlen($data);
            $sentBytes = socket_write($this->socket, $data, $length);

            if ($sentBytes === false || $sentBytes === $length) {
                break;
            }

            $data = substr($data, $sentBytes);
        }
    }

    protected function socketSelect($isRead): bool
    {
        $write = ! $isRead ? [$this->socket] : null;
        $read = $isRead ? [$this->socket] : null;
        $except = null;

        $selectResult = socket_select($read, $write, $except, $this->socketSecondsTimeout, $this->socketMicroSecondsTimeout);

        if ($selectResult === false || $selectResult <= 0) {
            return false;
        }

        return true;
    }
}
