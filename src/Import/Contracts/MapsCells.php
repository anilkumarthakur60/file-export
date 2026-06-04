<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Reads specific cell coordinates instead of iterating rows.
 *
 * Example: ['name' => 'B2', 'email' => 'B3']
 */
interface MapsCells
{
    /**
     * @return array<string, string>
     */
    public function cellMap(): array;
}
