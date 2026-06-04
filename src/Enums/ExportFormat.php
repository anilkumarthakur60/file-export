<?php

declare(strict_types=1);

namespace Anil\FileExport\Enums;

use Anil\FileExport\Exceptions\FileExportException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Writer\Ods as OdsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as DompdfWriter;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as MpdfWriter;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf as TcpdfWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Supported file formats for export.
 *
 * Delimited-text formats (CSV, TSV) are rendered by the package's own
 * streaming writer; the remaining formats are rendered via PhpSpreadsheet.
 * PDF requires one of mpdf/mpdf, dompdf/dompdf, or tecnickcom/tcpdf.
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
     * Whether this is a delimited text format rendered by the streaming writer.
     */
    public function isDelimitedText(): bool
    {
        return $this === self::CSV || $this === self::TSV;
    }

    /**
     * Whether this format is written by the package's own streaming CSV writer.
     */
    public function usesStreamingWriter(): bool
    {
        return $this->isDelimitedText();
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

    /**
     * Create the PhpSpreadsheet writer for this format.
     *
     * Not valid for delimited-text formats (CSV/TSV) — those are streamed by
     * the package's own writer.
     *
     * @throws FileExportException
     */
    public function makeWriter(Spreadsheet $spreadsheet): IWriter
    {
        return match ($this) {
            self::XLSX => new XlsxWriter($spreadsheet),
            self::ODS => new OdsWriter($spreadsheet),
            self::HTML => new HtmlWriter($spreadsheet),
            self::PDF => $this->makePdfWriter($spreadsheet),
            self::CSV, self::TSV => throw FileExportException::streamingFormatHasNoWriter($this->value),
        };
    }

    /**
     * Resolve an available PDF writer, preferring mpdf, then dompdf, then tcpdf.
     *
     * @throws FileExportException
     */
    private function makePdfWriter(Spreadsheet $spreadsheet): IWriter
    {
        if (class_exists(\Mpdf\Mpdf::class)) {
            return new MpdfWriter($spreadsheet);
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            return new DompdfWriter($spreadsheet);
        }

        if (class_exists(\TCPDF::class)) {
            return new TcpdfWriter($spreadsheet);
        }

        throw FileExportException::pdfDriverMissing();
    }
}
