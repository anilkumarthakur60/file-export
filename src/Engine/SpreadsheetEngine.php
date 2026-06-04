<?php

declare(strict_types=1);

namespace Anil\FileExport\Engine;

use Anil\FileExport\Engine\Contracts\ExportEngine;
use Anil\FileExport\Engine\Contracts\ExportSheet;
use Anil\FileExport\Engine\Contracts\RendersHtml;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Events\ExportWritten;
use Anil\FileExport\Events\SheetWritten;
use Anil\FileExport\Events\WritingExport;
use Anil\FileExport\Events\WritingSheet;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemManager;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\BaseWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * The package's own export engine, built on PhpSpreadsheet.
 *
 * Delimited-text formats (CSV/TSV) are streamed row-by-row for unbounded size;
 * all other formats are rendered through PhpSpreadsheet writers. Output is
 * always rendered to a temp file first, then delivered (copied to a disk,
 * streamed as a download, or returned as a string).
 */
final class SpreadsheetEngine implements ExportEngine
{
    /** Rows buffered per fromArray() call when building a spreadsheet. */
    private const WRITE_CHUNK = 1000;

    public function __construct(
        private readonly SheetStyler $styler,
        private readonly StreamingCsvWriter $csvWriter,
        private readonly FilesystemManager $filesystem,
        private readonly Dispatcher $events,
    ) {}

    public function store(ExportDocument $document, string $disk, string $path, ExportFormat $format): bool
    {
        $temporaryFile = $this->render($document, $format);

        try {
            $stream = fopen($temporaryFile, 'rb');

            if ($stream === false) {
                return false;
            }

            try {
                return $this->filesystem->disk($disk)->writeStream($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } finally {
            @unlink($temporaryFile);
        }
    }

    public function download(ExportDocument $document, string $filename, ExportFormat $format): BinaryFileResponse
    {
        $temporaryFile = $this->render($document, $format);

        $disposition = HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return (new BinaryFileResponse($temporaryFile, 200, [
            'Content-Type' => $format->mimeType(),
            'Content-Disposition' => $disposition,
        ]))->deleteFileAfterSend(true);
    }

    public function raw(ExportDocument $document, ExportFormat $format): string
    {
        $temporaryFile = $this->render($document, $format);

        try {
            $contents = file_get_contents($temporaryFile);

            return $contents === false ? '' : $contents;
        } finally {
            @unlink($temporaryFile);
        }
    }

    /**
     * Render the document to a temporary file and return its path.
     */
    private function render(ExportDocument $document, ExportFormat $format): string
    {
        $temporaryFile = (string) tempnam(sys_get_temp_dir(), 'fexp_');

        if ($format->usesStreamingWriter()) {
            $this->csvWriter->write($document->firstSheet(), $temporaryFile, $format);

            return $temporaryFile;
        }

        $spreadsheet = $this->buildSpreadsheet($document);

        try {
            $writer = $format->makeWriter($spreadsheet);

            if ($writer instanceof BaseWriter) {
                $writer->setPreCalculateFormulas($document->precalculateFormulas);
            }

            $writer->save($temporaryFile);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $temporaryFile;
    }

    private function buildSpreadsheet(ExportDocument $document): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $this->applyProperties($spreadsheet, $document->properties);
        $this->applyDocumentDefaults($spreadsheet, $document);

        $this->events->dispatch(new WritingExport($spreadsheet, $document));

        $index = 0;
        foreach ($document->sheets as $sheet) {
            $worksheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $worksheet->setTitle($this->safeTitle($sheet->title(), $index));

            $this->renderSheet($spreadsheet, $worksheet, $sheet);

            $index++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $this->events->dispatch(new ExportWritten($spreadsheet, $document));

        return $spreadsheet;
    }

    private function renderSheet(Spreadsheet $spreadsheet, Worksheet $worksheet, ExportSheet $sheet): void
    {
        $binder = $sheet->valueBinder();
        if ($binder !== null) {
            Cell::setValueBinder($binder);
        }

        try {
            $this->events->dispatch(new WritingSheet($worksheet, $sheet));

            if ($sheet instanceof RendersHtml) {
                $this->loadHtml($spreadsheet, $worksheet, $sheet);
            } else {
                $this->writeSheet($worksheet, $sheet);
                $this->styler->apply($worksheet, $sheet);
            }

            $this->applySheetExtras($worksheet, $sheet);

            $this->events->dispatch(new SheetWritten($worksheet, $sheet));
        } finally {
            if ($binder !== null) {
                Cell::setValueBinder(new DefaultValueBinder);
            }
        }
    }

    private function writeSheet(Worksheet $worksheet, ExportSheet $sheet): void
    {
        [$column, $startRow] = Coordinate::coordinateFromString($sheet->startCellCoordinate());
        $startRow = (int) $startRow;
        $strict = $sheet->strictNullComparison();

        $headings = $sheet->headings();
        if ($headings !== []) {
            $worksheet->fromArray($headings, null, $column.$startRow, $strict);
            $startRow++;
        }

        $rowNumber = $startRow;
        $buffer = [];

        foreach ($sheet->rows() as $row) {
            $buffer[] = $row;

            if (count($buffer) >= self::WRITE_CHUNK) {
                $worksheet->fromArray($buffer, null, $column.$rowNumber, $strict);
                $rowNumber += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $worksheet->fromArray($buffer, null, $column.$rowNumber, $strict);
        }
    }

    private function loadHtml(Spreadsheet $spreadsheet, Worksheet $worksheet, RendersHtml $sheet): void
    {
        $temporaryHtml = sys_get_temp_dir().'/'.uniqid('fexp_html_', true).'.html';
        file_put_contents($temporaryHtml, $sheet->html());

        try {
            $reader = new HtmlReader;
            $reader->setSheetIndex($spreadsheet->getIndex($worksheet));
            $reader->loadIntoExisting($temporaryHtml, $spreadsheet);
        } finally {
            @unlink($temporaryHtml);
        }
    }

    private function applySheetExtras(Worksheet $worksheet, ExportSheet $sheet): void
    {
        $callback = $sheet->styleCallback();
        if ($callback !== null) {
            $callback($worksheet);
        }

        foreach ($sheet->charts() as $chart) {
            $worksheet->addChart($chart);
        }

        foreach ($sheet->drawings() as $drawing) {
            $drawing->setWorksheet($worksheet);
        }
    }

    private function applyDocumentDefaults(Spreadsheet $spreadsheet, ExportDocument $document): void
    {
        if ($document->defaultStyleCallback !== null) {
            ($document->defaultStyleCallback)($spreadsheet->getDefaultStyle());
        }

        $background = $document->background;
        if (is_string($background) && $background !== '') {
            $spreadsheet->getDefaultStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($background);
        } elseif (is_array($background)) {
            $spreadsheet->getDefaultStyle()->applyFromArray($background);
        }
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function applyProperties(Spreadsheet $spreadsheet, array $properties): void
    {
        $meta = $spreadsheet->getProperties();

        foreach ($properties as $key => $value) {
            match ($key) {
                'title' => $meta->setTitle($value),
                'creator' => $meta->setCreator($value),
                'company' => $meta->setCompany($value),
                'description' => $meta->setDescription($value),
                'subject' => $meta->setSubject($value),
                'keywords' => $meta->setKeywords($value),
                'category' => $meta->setCategory($value),
                'manager' => $meta->setManager($value),
                default => null,
            };
        }
    }

    /**
     * Sanitize a worksheet title to PhpSpreadsheet's constraints (≤31 chars,
     * no \ / ? * : [ ] characters).
     */
    private function safeTitle(string $title, int $index): string
    {
        $clean = (string) preg_replace('/[\\\\\/\?\*\:\[\]]/', ' ', $title);
        $clean = trim(mb_substr($clean, 0, 31));

        return $clean === '' ? 'Sheet'.($index + 1) : $clean;
    }
}
