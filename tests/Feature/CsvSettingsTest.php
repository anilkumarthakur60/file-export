<?php

declare(strict_types=1);

use Anil\FileExport\DataTransferObjects\CsvSettings;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Storage;

it('writes csv with custom delimiter and bom', function (): void {
    Storage::fake('local');
    seedUsers(2);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->format(ExportFormat::CSV)
        ->withCsvSettings(new CsvSettings(delimiter: ';', useBom: true))
        ->store();

    $contents = Storage::disk('local')->get('exports/export.csv');

    expect($contents)->toStartWith("\xEF\xBB\xBF")              // UTF-8 BOM
        ->and($contents)->toContain('"Name";"Email"')           // semicolon delimiter
        ->and($contents)->toContain('"User 1";"user1@example.com"');
});

it('falls back to the configured csv defaults', function (): void {
    config()->set('file-export.csv.delimiter', '|');
    Storage::fake('local');
    seedUsers(1);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->format(ExportFormat::CSV)
        ->store();

    expect(Storage::disk('local')->get('exports/export.csv'))->toContain('"Name"|"Email"');
});
