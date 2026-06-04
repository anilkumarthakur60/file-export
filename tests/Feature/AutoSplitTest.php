<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\MultiSheetExport;
use Anil\FileExport\Tests\Fixtures\User;
use Maatwebsite\Excel\Facades\Excel;

it('splits an oversized query export into multiple sheets', function (): void {
    Excel::fake();
    seedUsers(10);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(true, 4) // 10 rows / 4 per sheet = 3 sheets
        ->store();

    Excel::assertStored('exports/export.xlsx', 'local', function ($export): bool {
        $this->assertInstanceOf(MultiSheetExport::class, $export);
        $this->assertCount(3, $export->sheets());

        return true;
    });
});

it('continues serial numbers across split sheets', function (): void {
    Excel::fake();
    seedUsers(6);

    fileExporter()
        ->fromQuery(User::query()->orderBy('id'))
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->withSerialNumber()
        ->autoSplit(true, 4) // sheet 1: rows 1-4, sheet 2: rows 5-6
        ->store();

    Excel::assertStored('exports/export.xlsx', 'local', function (MultiSheetExport $export): bool {
        $sheets = $export->sheets();
        $this->assertCount(2, $sheets);

        // First mapped row on the second sheet should be serial #5.
        $secondSheetFirstRow = $sheets[1]->map(User::query()->orderBy('id')->skip(4)->first());
        $this->assertSame(5, $secondSheetFirstRow[0]);

        return true;
    });
});

it('does not split csv exports', function (): void {
    Excel::fake();
    seedUsers(10);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->format(ExportFormat::CSV)
        ->autoSplit(true, 4)
        ->store();

    Excel::assertStored('exports/export.csv', 'local', function ($export): bool {
        $this->assertNotInstanceOf(MultiSheetExport::class, $export);

        return true;
    });
});

it('enforces max rows when auto split disabled', function (): void {
    Excel::fake();
    seedUsers(10);

    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('exceeds the configured maximum');

    config()->set('file-export.max_rows', 5);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(false)
        ->store();
});

it('does not split collections', function (): void {
    Excel::fake();

    fileExporter()
        ->fromCollection(collect(range(1, 10))->map(fn ($n) => ['n' => $n]))
        ->withHeaders(['N'])
        ->withMapping(fn (array $row) => [$row['n']])
        ->autoSplit(true, 4)
        ->store();

    Excel::assertStored('exports/export.xlsx', 'local', function ($export): bool {
        $this->assertInstanceOf(CollectionExport::class, $export);

        return true;
    });
});
