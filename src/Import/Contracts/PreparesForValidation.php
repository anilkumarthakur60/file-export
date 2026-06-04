<?php

declare(strict_types=1);

namespace Anil\FileExport\Import\Contracts;

/**
 * Transforms a row before validation/consumption.
 */
interface PreparesForValidation
{
    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public function prepareForValidation(array $row, int $index): array;
}
