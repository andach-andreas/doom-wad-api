<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from DatabaseOutput when a running CommandRun has been asked to stop,
 * unwinding the artisan command at its next write.
 */
class CommandCancelledException extends RuntimeException
{
}
