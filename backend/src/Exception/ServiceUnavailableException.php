<?php

declare(strict_types=1);

namespace App\Exception;

/** A required infrastructure dependency (e.g. the message queue) is not reachable. */
final class ServiceUnavailableException extends AppException
{
}
