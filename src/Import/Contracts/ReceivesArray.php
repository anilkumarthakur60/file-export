<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Receives all read rows as a single array.
 */
interface ReceivesArray
{
    /**
     * @param  array<int, array<int|string, mixed>>  $rows
     */
    public function array(array $rows): void;
}
