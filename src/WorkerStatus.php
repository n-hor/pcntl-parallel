<?php
/**
 * Author: Nikolay Horobets.
 *
 * @link https://github.com/n-hor
 * @link https://sandbox.ws
 */

namespace NHor\PcntlParallel;

enum WorkerStatus: int
{
    case Killed = 0;
    case Active = 1;
    case Idle = 2;
}
