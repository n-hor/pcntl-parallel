<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel\Contracts;

interface Packer
{
    public function pack(string $content): array;

    public function unpack(string $content): array;
}
