<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Contracts;

interface Packer
{
    public function pack(string $content): array;

    public function unpack(string $content): array;
}
