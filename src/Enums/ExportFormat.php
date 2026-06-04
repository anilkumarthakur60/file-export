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

    /**
     * Whether this format imposes a per-sheet row limit.
     *
     * Spreadsheet formats (XLSX, ODS) are capped at 1,048,576 rows per sheet,
     * and PDF is impractical beyond a similar scale. CSV, TSV, and HTML stream
     * row-by-row with no inherent limit.
     */
    public function hasRowLimit(): bool
    {
        return match ($this) {
            self::XLSX, self::ODS, self::PDF => true,
            self::CSV, self::TSV, self::HTML => false,
        };
    }

    /**
     * Whether this is a delimited text format that honours CSV settings.
     */
    public function isDelimitedText(): bool
    {
        return $this === self::CSV || $this === self::TSV;
    }

    /**
     * The MIME type for this format.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
            self::TSV => 'text/tab-separated-values',
            self::ODS => 'application/vnd.oasis.opendocument.spreadsheet',
            self::HTML => 'text/html',
            self::PDF => 'application/pdf',
        };
    }
}
