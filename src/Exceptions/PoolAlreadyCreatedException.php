<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel\Exceptions;

use Exception;

class PoolAlreadyCreatedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Pool already created.');
    }
}
