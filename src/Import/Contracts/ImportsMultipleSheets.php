<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Routes each worksheet to its own importable.
 */
interface ImportsMultipleSheets
{
    /**
     * Map of sheet name OR zero-based index => importable object.
     *
     * @return array<int|string, object>
     */
    public function sheets(): array;
}
