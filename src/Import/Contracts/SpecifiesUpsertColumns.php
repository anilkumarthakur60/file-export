<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Restricts which columns an upsert updates (otherwise all are updated).
 */
interface SpecifiesUpsertColumns
{
    /**
     * @return array<int, string>
     */
    public function upsertColumns(): array;
}
