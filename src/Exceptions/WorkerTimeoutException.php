<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 */

namespace NHor\PcntlParallel\Exceptions;

use Exception;

class WorkerTimeoutException extends Exception
{
    public function __construct(int $timeout)
    {
        parent::__construct('Worker killed by timeout: ' . $timeout . ' seconds.');
    }
}
