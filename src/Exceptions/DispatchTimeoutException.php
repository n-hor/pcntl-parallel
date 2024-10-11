<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Exceptions;

use Exception;

class DispatchTimeoutException extends Exception
{
    public function __construct(int $timeout)
    {
        parent::__construct('Dispatch failed by timeout: ' . $timeout . ' microseconds.');
    }
}
