<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Anil\FileExport\Events\ImportRead;
use Anil\FileExport\Events\ReadingImport;
use Anil\FileExport\Events\SheetRead;
use Anil\FileExport\Import\Contracts\EvaluatesFormulas;
use Anil\FileExport\Import\Contracts\FormatsCellData;
use Anil\FileExport\Import\Contracts\HandlesEachRow;
use Anil\FileExport\Import\Contracts\ImportsMultipleSheets;
use Anil\FileExport\Import\Contracts\LimitsColumns;
use Anil\FileExport\Import\Contracts\LimitsRows;
use Anil\FileExport\Import\Contracts\MapsCells;
use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\PreparesForValidation;
use Anil\FileExport\Import\Contracts\ReceivesArray;
use Anil\FileExport\Import\Contracts\ReceivesCollection;
use Anil\FileExport\Import\Contracts\SkipsEmptyRows;
use Anil\FileExport\Import\Contracts\StartsAtRow;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Anil\FileExport\Import\Contracts\ValidatesRows;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a spreadsheet file via PhpSpreadsheet and drives an importable through
 * the import contracts: heading rows, start/limit/columns, validation, row
 * handlers, model mapping (batch/upsert/skip-duplicates), mapped cells, and
 * multi-sheet routing.
 */
class SpreadsheetReader
{
    public function __construct(
        private readonly HeadingFormatter $headingFormatter,
        private readonly RowValidator $rowValidator,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Read every sheet into a nested array of rows (no heading mapping).
     *
     * @return array<int, array<int, array<int|string, mixed>>>
     */
    public function toArray(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheets[] = $this->readRows($worksheet, 1, $worksheet->getHighestRow(), [], $worksheet->getHighestColumn(), false);
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }

    /**
     * Read every sheet into a Collection of row Collections.
     *
     * @return Collection<int, Collection<int, array<int|string, mixed>>>
     */
    public function toCollection(string $path): Collection
    {
        return (new Collection($this->toArray($path)))
            ->map(static fn (array $rows): Collection => new Collection($rows));
    }

    /**
     * Drive an importable over the file (routing multi-sheet importables).
     */
    public function import(object $importable, string $path): void
    {
        $spreadsheet = IOFactory::load($path);
        $this->events->dispatch(new ReadingImport($importable));

        try {
            if ($importable instanceof ImportsMultipleSheets) {
                foreach ($importable->sheets() as $key => $sheetImportable) {
                    $worksheet = is_int($key)
                        ? ($spreadsheet->getSheetCount() > $key ? $spreadsheet->getSheet($key) : null)
                        : $spreadsheet->getSheetByName($key);

                    if ($worksheet !== null) {
                        $this->importSheet($sheetImportable, $worksheet);
                    }
                }
            } else {
                $this->importSheet($importable, $spreadsheet->getActiveSheet());
            }

            $this->events->dispatch(new ImportRead($importable));
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function importSheet(object $importable, Worksheet $worksheet): void
    {
        if ($importable instanceof MapsCells) {
            $this->importMappedCells($importable, $worksheet);
            $this->events->dispatch(new SheetRead($importable, $worksheet));

            return;
        }

        $startRow = $this->determineStartRow($importable);
        $endRow = $worksheet->getHighestRow();
        if ($importable instanceof LimitsRows) {
            $endRow = min($endRow, $startRow + $importable->limit() - 1);
        }

        $this->processWindow($importable, $worksheet, $startRow, $endRow);

        $this->events->dispatch(new SheetRead($importable, $worksheet));
    }

    /**
     * Load only a row window from a file (via a read filter) and import it.
     */
    public function importChunk(object $importable, string $path, int $startRow, int $endRow): void
    {
        $headingRow = $importable instanceof UsesHeadingRow ? $importable->headingRow() : 0;

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadFilter(new ChunkReadFilter($startRow, $endRow, $headingRow));
        $spreadsheet = $reader->load($path);

        try {
            $this->importWindow($importable, $spreadsheet->getActiveSheet(), $startRow, $endRow);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Total rows in the first worksheet (cheap — reads sheet info only).
     */
    public function rowCount(string $path): int
    {
        $reader = IOFactory::createReaderForFile($path);

        if (! method_exists($reader, 'listWorksheetInfo')) {
            return 0;
        }

        $info = $reader->listWorksheetInfo($path);
        if (! is_array($info)) {
            return 0;
        }

        $first = $info[0] ?? null;
        $total = is_array($first) ? ($first['totalRows'] ?? 0) : 0;

        return is_numeric($total) ? (int) $total : 0;
    }

    /**
     * The first data row for an importable (after any heading row).
     */
    public function dataStartRow(object $importable): int
    {
        return $this->determineStartRow($importable);
    }

    /**
     * Import an explicit row window (used by chunked imports).
     */
    public function importWindow(object $importable, Worksheet $worksheet, int $startRow, int $endRow): void
    {
        if ($importable instanceof MapsCells) {
            $this->importMappedCells($importable, $worksheet);

            return;
        }

        $this->processWindow($importable, $worksheet, $startRow, $endRow);
    }

    private function processWindow(object $importable, Worksheet $worksheet, int $startRow, int $endRow): void
    {
        $endColumn = $importable instanceof LimitsColumns
            ? $importable->endColumn()
            : $worksheet->getHighestColumn();

        $evaluate = $importable instanceof EvaluatesFormulas;
        $format = $importable instanceof FormatsCellData;

        $headings = [];
        if ($importable instanceof UsesHeadingRow) {
            $headings = $this->headingFormatter->format(
                $this->rowValues($worksheet, $importable->headingRow(), $endColumn, $evaluate, $format)
            );
        }

        $skipEmpty = $importable instanceof SkipsEmptyRows;
        $modelImporter = $importable instanceof MapsToModel ? new ModelImporter($importable) : null;
        $collected = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $row = new ImportRow($this->rowValues($worksheet, $rowNumber, $endColumn, $evaluate, $format), $headings, $rowNumber);

            if ($skipEmpty && $row->isEmpty()) {
                continue;
            }

            $data = $row->toArray();

            if ($importable instanceof PreparesForValidation) {
                $data = $importable->prepareForValidation($data, $rowNumber);
            }

            if ($importable instanceof ValidatesRows && ! $this->rowValidator->passes($importable, $data, $rowNumber)) {
                continue;
            }

            if ($importable instanceof HandlesEachRow) {
                $importable->onRow($row);
            }

            $modelImporter?->add($data);

            if ($importable instanceof ReceivesArray || $importable instanceof ReceivesCollection) {
                $collected[] = $data;
            }
        }

        $modelImporter?->flush();

        if ($importable instanceof ReceivesArray) {
            $importable->array($collected);
        }

        if ($importable instanceof ReceivesCollection) {
            $importable->collection(new Collection($collected));
        }
    }

    private function importMappedCells(MapsCells $importable, Worksheet $worksheet): void
    {
        $evaluate = $importable instanceof EvaluatesFormulas;
        $format = $importable instanceof FormatsCellData;

        $data = [];
        foreach ($importable->cellMap() as $key => $coordinate) {
            $data[$key] = $this->cellValue($worksheet, $coordinate, $evaluate, $format);
        }

        if ($importable instanceof PreparesForValidation) {
            $data = $importable->prepareForValidation($data, 0);
        }

        if ($importable instanceof ValidatesRows && ! $this->rowValidator->passes($importable, $data, 0)) {
            return;
        }

        if ($importable instanceof MapsToModel) {
            $modelImporter = new ModelImporter($importable);
            $modelImporter->add($data);
            $modelImporter->flush();
        }

        if ($importable instanceof ReceivesArray) {
            $importable->array([$data]);
        }

        if ($importable instanceof ReceivesCollection) {
            $importable->collection(new Collection([$data]));
        }
    }

    private function determineStartRow(object $importable): int
    {
        if ($importable instanceof StartsAtRow) {
            return $importable->startRow();
        }

        if ($importable instanceof UsesHeadingRow) {
            return $importable->headingRow() + 1;
        }

        return 1;
    }

    /**
     * @param  array<int, string>  $headings
     * @return array<int, array<int|string, mixed>>
     */
    private function readRows(Worksheet $worksheet, int $startRow, int $endRow, array $headings, string $endColumn, bool $skipEmpty): array
    {
        $rows = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $row = new ImportRow($this->rowValues($worksheet, $rowNumber, $endColumn, false, false), $headings, $rowNumber);

            if ($skipEmpty && $row->isEmpty()) {
                continue;
            }

            $rows[] = $row->toArray();
        }

        return $rows;
    }

    /**
     * Read a single row's cell values from column A to $endColumn.
     *
     * @return array<int, mixed>
     */
    private function rowValues(Worksheet $worksheet, int $rowNumber, string $endColumn, bool $evaluate, bool $format): array
    {
        $values = [];
        $lastColumn = Coordinate::columnIndexFromString($endColumn);

        for ($column = 1; $column <= $lastColumn; $column++) {
            $values[] = $this->cellValue($worksheet, Coordinate::stringFromColumnIndex($column).$rowNumber, $evaluate, $format);
        }

        return $values;
    }

    private function cellValue(Worksheet $worksheet, string $coordinate, bool $evaluate, bool $format): mixed
    {
        $cell = $worksheet->getCell($coordinate);
        $value = $evaluate ? $cell->getCalculatedValue() : $cell->getValue();

        if ($format && is_scalar($value)) {
            return NumberFormat::toFormattedString(
                $value,
                $cell->getStyle()->getNumberFormat()->getFormatCode() ?? NumberFormat::FORMAT_GENERAL,
            );
        }

        return $value;
    }
}
