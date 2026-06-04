<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

use Anil\FileExport\Import\ImportRow;

/**
 * Handles each read row individually as it is streamed from the file.
 */
interface HandlesEachRow
{
    public function onRow(ImportRow $row): void;
}
