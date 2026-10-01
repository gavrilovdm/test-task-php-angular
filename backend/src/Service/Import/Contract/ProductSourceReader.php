<?php

declare(strict_types=1);

namespace App\Service\Import\Contract;

use App\Service\Import\InvalidImportFileException;
use App\Service\Import\Source\ProductSource;

/** Reads a tabular import file (xlsx today; csv etc. can be added as new implementations). */
interface ProductSourceReader
{
    /** @throws InvalidImportFileException */
    public function read(string $path): ProductSource;
}
