<?php

declare(strict_types=1);

namespace App\Service\Import;

/** The file as a whole cannot be processed (unreadable, empty, missing required columns, ...). */
final class InvalidImportFileException extends \RuntimeException
{
}
