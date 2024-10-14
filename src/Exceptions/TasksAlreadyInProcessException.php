<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel\Exceptions;

use Exception;

class TasksAlreadyInProcessException extends Exception
{
    public function __construct()
    {
        parent::__construct('Tasks already in process.');
    }
}
