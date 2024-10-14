<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel\Serializers;

use NHor\PcntlParallel\Contracts\Serializer;

class NativeSerializer implements Serializer
{
    public function serialize(mixed $content): string
    {
        return serialize($content);
    }

    public function unserialize(string $data): mixed
    {
        return unserialize($data);
    }
}
