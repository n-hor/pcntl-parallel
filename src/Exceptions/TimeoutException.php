<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Exceptions;

use Exception;

class TimeoutException extends Exception
{
    public function __construct(float $timeout)
    {
        parent::__construct('Wait timeout exception: ' . $timeout . ' seconds.');
    }
}
