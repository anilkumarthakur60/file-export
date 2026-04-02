<?php

declare(strict_types=1);

namespace Anil\FileExport\Enums;

use Maatwebsite\Excel\Excel;

/**
 * Supported file formats for export.
 *
 * PDF format requires the mpdf/mpdf package to be installed.
 */
enum ExportFormat: string
{
    case XLSX = 'xlsx';
    case CSV = 'csv';
    case TSV = 'tsv';
    case ODS = 'ods';
    case HTML = 'html';
    case PDF = 'pdf';

    /**
     * Get the Maatwebsite writer type constant for this format.
     */
    public function writerType(): string
    {
        return match ($this) {
            self::XLSX => Excel::XLSX,
            self::CSV => Excel::CSV,
            self::TSV => Excel::TSV,
            self::ODS => Excel::ODS,
            self::HTML => Excel::HTML,
            self::PDF => Excel::MPDF,
        };
    }

    /**
     * Get the file extension for this format.
     */
    public function extension(): string
    {
        return $this->value;
    }
}
