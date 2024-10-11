<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Packers;

use NHor\PcntlParallel\Contracts;
use NHor\PcntlParallel\Exceptions\PackerException;

class DefaultPacker implements Contracts\Packer
{
    public const MESSAGE_START_BYTE = 0x02;

    protected int $incomingMessageLength = 0;

    protected int $decodedMessageLength = 0;

    protected string $decodedContentBuffer = '';

    public function __construct(protected int $bufferSize)
    {
        if ($this->bufferSize < 6) {
            throw new PackerException('Invalid buffer size');
        }
    }

    public function pack(string $content): array
    {
        $startByte = chr(self::MESSAGE_START_BYTE);
        $length = strlen($content);

        $packedLength = pack('N', $length);

        $fullMessage = $startByte . $packedLength . $content;

        return str_split($fullMessage, $this->bufferSize);
    }

    public function unpack(string $content): ?string
    {
        if ($content === '') {
            $this->flushBuffer();
            return $content;
        }

        $startByte = chr(self::MESSAGE_START_BYTE);

        if ($content[0] !== $startByte) {
            $this->updateBuffer($content);

            if ($this->incomingMessageLength === $this->decodedMessageLength) {
                $fullMessage = $this->decodedContentBuffer;
                $this->flushBuffer();
                return $fullMessage;
            }

            return null;
        }

        $packedLength = substr($content, 1, 4);
        [,$realContentLength] = unpack('N', $packedLength);
        $this->incomingMessageLength = $realContentLength;
        $message = substr($content, 5);
        $currentChunkLength = strlen($message);

        if ($currentChunkLength !== $this->incomingMessageLength) {
            $this->updateBuffer($message);
            return null;
        }

        return $message;
    }

    protected function updateBuffer(string $message): void
    {
        $this->decodedContentBuffer .= $message;
        $this->decodedMessageLength += strlen($message);
    }

    protected function flushBuffer(): void
    {
        $this->decodedContentBuffer = '';
        $this->decodedMessageLength = 0;
        $this->incomingMessageLength = 0;
    }
}
