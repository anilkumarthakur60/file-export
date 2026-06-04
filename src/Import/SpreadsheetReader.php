<?php

declare(strict_types=1);

namespace Anil\FileExport\Import;

use Anil\FileExport\Import\Contracts\HandlesEachRow;
use Anil\FileExport\Import\Contracts\LimitsColumns;
use Anil\FileExport\Import\Contracts\LimitsRows;
use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\ReceivesArray;
use Anil\FileExport\Import\Contracts\ReceivesCollection;
use Anil\FileExport\Import\Contracts\SkipsEmptyRows;
use Anil\FileExport\Import\Contracts\StartsAtRow;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a spreadsheet file via PhpSpreadsheet and drives an importable through
 * the import contracts (heading rows, start/limit/columns, row handlers,
 * model mapping, array/collection collectors).
 */
class SpreadsheetReader
{
    public function __construct(
        private readonly HeadingFormatter $headingFormatter,
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
     * Drive an importable over the first worksheet.
     */
    public function import(object $importable, string $path): void
    {
        $spreadsheet = IOFactory::load($path);

        try {
            $this->importSheet($importable, $spreadsheet->getActiveSheet());
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function importSheet(object $importable, Worksheet $worksheet): void
    {
        $endColumn = $importable instanceof LimitsColumns
            ? $importable->endColumn()
            : $worksheet->getHighestColumn();

        $headings = [];
        if ($importable instanceof UsesHeadingRow) {
            $headings = $this->headingFormatter->format(
                $this->rowValues($worksheet, $importable->headingRow(), $endColumn)
            );
        }

        $startRow = $this->determineStartRow($importable);
        $endRow = $worksheet->getHighestRow();
        if ($importable instanceof LimitsRows) {
            $endRow = min($endRow, $startRow + $importable->limit() - 1);
        }

        $skipEmpty = $importable instanceof SkipsEmptyRows;
        $collected = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $row = new ImportRow($this->rowValues($worksheet, $rowNumber, $endColumn), $headings, $rowNumber);

            if ($skipEmpty && $row->isEmpty()) {
                continue;
            }

            if ($importable instanceof HandlesEachRow) {
                $importable->onRow($row);
            }

            if ($importable instanceof MapsToModel) {
                $this->persist($importable, $row->toArray());
            }

            if ($importable instanceof ReceivesArray || $importable instanceof ReceivesCollection) {
                $collected[] = $row->toArray();
            }
        }

        if ($importable instanceof ReceivesArray) {
            $importable->array($collected);
        }

        if ($importable instanceof ReceivesCollection) {
            $importable->collection(new Collection($collected));
        }
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    protected function persist(MapsToModel $importable, array $row): void
    {
        $model = $importable->model($row);

        if ($model === null) {
            return;
        }

        foreach (is_array($model) ? $model : [$model] as $single) {
            $single->save();
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
            $row = new ImportRow($this->rowValues($worksheet, $rowNumber, $endColumn), $headings, $rowNumber);

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
    private function rowValues(Worksheet $worksheet, int $rowNumber, string $endColumn): array
    {
        $values = [];
        $lastColumn = Coordinate::columnIndexFromString($endColumn);

        for ($column = 1; $column <= $lastColumn; $column++) {
            $values[] = $worksheet->getCell(Coordinate::stringFromColumnIndex($column).$rowNumber)->getValue();
        }

        return $values;
    }
}
