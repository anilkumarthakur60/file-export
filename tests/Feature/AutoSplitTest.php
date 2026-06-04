<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('splits an oversized query export into multiple sheets', function (): void {
    Storage::fake('local');
    seedUsers(10);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(true, 4) // 10 rows / 4 per sheet = 3 sheets
        ->store();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path('exports/export.xlsx'));

    expect($spreadsheet->getSheetCount())->toBe(3);
});

it('continues serial numbers across split sheets', function (): void {
    Storage::fake('local');
    seedUsers(6);

    fileExporter()
        ->fromQuery(User::query()->orderBy('id'))
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->withSerialNumber()
        ->autoSplit(true, 4) // sheet 1: rows 1-4, sheet 2: rows 5-6
        ->store();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path('exports/export.xlsx'));

    expect($spreadsheet->getSheetCount())->toBe(2);

    // First data row of the second sheet (A1 is the "S.N." header) continues at 5.
    expect((int) $spreadsheet->getSheet(1)->getCell('A2')->getValue())->toBe(5);
});

it('does not split csv exports', function (): void {
    Storage::fake('local');
    seedUsers(10);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->format(ExportFormat::CSV)
        ->autoSplit(true, 4)
        ->store();

    $contents = Storage::disk('local')->get('exports/export.csv');

    // One single CSV file containing every row (header + 10 rows).
    expect($contents)->toContain('User 1')
        ->and($contents)->toContain('User 10')
        ->and(substr_count((string) $contents, "\n"))->toBe(11);
});

it('enforces max rows when auto split disabled', function (): void {
    Storage::fake('local');
    seedUsers(10);

    config()->set('file-export.max_rows', 5);

    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('exceeds the configured maximum');

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(false)
        ->store();
});

it('does not split collections', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromCollection(collect(range(1, 10))->map(fn ($n) => ['n' => $n]))
        ->withHeaders(['N'])
        ->withMapping(fn (array $row) => [$row['n']])
        ->autoSplit(true, 4)
        ->store();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path('exports/export.xlsx'));

    expect($spreadsheet->getSheetCount())->toBe(1);
});
