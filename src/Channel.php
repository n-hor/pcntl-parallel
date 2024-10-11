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

    protected string $buffer = '';

    public function __construct(protected Socket $socket, protected int $bufferSize, protected Serializer $serializer, protected DefaultPacker $packer)
    {
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

            if ($output === false) {
                yield self::NO_CONTENT;
            }

            if ($output === '') {
                yield '';
            }

            $message = $this->packer->unpack($output);

            if ($message !== null) {
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

    protected function write(string $data): false|int
    {
        socket_set_nonblock($this->socket);
        return socket_write($this->socket, $data, strlen($data));
    }
}
