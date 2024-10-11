<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Contracts;

interface Serializer
{
    public function serialize(mixed $content): string;

    public function unserialize(string $data): mixed;
}
