<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel\Messages;

use Throwable;

class WorkerExceptionMessage
{
    public string $message;

    public string $stacktrace;

    public string $exceptionClass;

    public function __construct(Throwable $exception)
    {
        $this->exceptionClass = $exception::class;
        $this->stacktrace = $exception->getTraceAsString();
        $this->message = $exception->getMessage();
    }
}
