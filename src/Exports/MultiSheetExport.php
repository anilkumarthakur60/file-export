<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;

/**
 * Wraps multiple sheet exports into a single workbook.
 *
 * Each sheet can have its own data source, headers, mapping, and styling.
 * Document-level properties (title, creator, etc.) are set on this wrapper.
 */
class MultiSheetExport implements WithMultipleSheets, WithProperties
{
    /**
     * @param  array<int, CollectionExport|QueryExport>  $sheets  The individual sheet exports.
     * @param  array<string, string>  $documentProperties  Document metadata (title, creator, company, etc.).
     */
    public function __construct(
        private readonly array $sheets,
        private readonly array $documentProperties = [],
    ) {}

    /**
     * @return array<int, CollectionExport|QueryExport>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }

    /**
     * @return array<string, string>
     */
    public function properties(): array
    {
        return $this->documentProperties;
    }
}
