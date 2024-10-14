<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Packers;

use NHor\PcntlParallel\Contracts\Packer;
use NHor\PcntlParallel\Exceptions\PackerException;

class DefaultPacker implements Packer
{
    public const MESSAGE_START_BYTE = 0x01;

    protected int $incomingMessageLength = 0;

    protected int $decodedMessageLength = 0;

    protected int $reservedStringLength = 5;

    protected string $decodedContentBuffer = '';

    public function __construct(protected int $bufferSize)
    {
        if ($this->bufferSize < 6) {
            throw new PackerException('Invalid buffer size');
        }
    }

    public function parseMessage(string $content, &$result = []): void
    {
        if ($content === '') {
            $this->flushBuffer();
            return;
        }

        if ($content[0] === $this->getStartByte()) {
            // parse message length
            $packedLength = substr($content, 1, 4);
            [,$realContentLength] = unpack('N', $packedLength);
            $this->incomingMessageLength = $realContentLength;

            $message = substr($content, $this->reservedStringLength, $this->incomingMessageLength);

            $nextMessage = substr($content, $this->reservedStringLength + $this->incomingMessageLength);
        } else {
            $currentMessageLength = $this->incomingMessageLength - $this->decodedMessageLength;
            $message = substr($content, 0, $currentMessageLength);

            $nextMessage = substr($content, $currentMessageLength);
        }

        $this->updateBuffer($message);

        if ($this->incomingMessageLength === $this->decodedMessageLength) {
            $result[] = $this->decodedContentBuffer;
            $this->flushBuffer();
        }
        // parse second message that comes with first
        if (strlen($nextMessage) > 0) {
            $this->parseMessage($nextMessage, $result);
        }
    }

    public function pack(string $content): array
    {
        $length = strlen($content);
        $packedLength = pack('N', $length);
        $fullMessage = $this->getStartByte() . $packedLength . $content;

        return str_split($fullMessage, $this->bufferSize);
    }

    public function unpack(string $content): array
    {
        $this->parseMessage($content, $result);
        return $result ?? [];
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

    protected function getStartByte(): string
    {
        return chr(self::MESSAGE_START_BYTE);
    }
}
