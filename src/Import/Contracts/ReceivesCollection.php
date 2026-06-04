<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

use Illuminate\Support\Collection;

/**
 * Receives all read rows as a Collection.
 */
interface ReceivesCollection
{
    /**
     * @param  Collection<int, array<int|string, mixed>>  $rows
     */
    public function collection(Collection $rows): void;
}
