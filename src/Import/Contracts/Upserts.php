<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Persists mapped models with an upsert (insert or update) keyed by uniqueBy().
 */
interface Upserts
{
    /**
     * @return string|array<int, string>
     */
    public function uniqueBy(): string|array;
}
