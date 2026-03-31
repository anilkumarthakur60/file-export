<?php

declare(strict_types=1);

namespace Anil\FileExport\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;

class MultiSheetExport implements WithMultipleSheets, WithProperties
{
    /**
     * @param  array<int, CollectionExport|QueryExport>  $sheets
     * @param  array<string, string>  $documentProperties
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
