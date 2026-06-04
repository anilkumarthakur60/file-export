<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Services\FileExporter;
use Anil\FileExport\Tests\Fixtures\User;
use Maatwebsite\Excel\Facades\Excel;

it('reports the format and mime type on the result', function (): void {
    Excel::fake();
    seedUsers(1);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->store();

    expect($result->format)->toBe('xlsx')
        ->and($result->mimeType)->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($result->toArray())->toMatchArray([
            'success' => true,
            'format' => 'xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
});

it('reports csv format and mime type', function (): void {
    Excel::fake();
    seedUsers(1);

    $result = fileExporter()
        ->fromCollection(collect([['n' => 1]]))
        ->withHeaders(['N'])
        ->withMapping(fn (array $r) => [$r['n']])
        ->format(ExportFormat::CSV)
        ->store();

    expect($result->format)->toBe('csv')
        ->and($result->mimeType)->toBe('text/csv');
});

it('applies the when() helper conditionally', function (): void {
    Excel::fake();
    seedUsers(1);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->when(true, fn (FileExporter $e) => $e->filename('conditional'))
        ->when(false, fn (FileExporter $e) => $e->filename('never'))
        ->store();

    expect($result->filename)->toBe('conditional.xlsx');
});

it('can be extended with a macro', function (): void {
    Excel::fake();
    seedUsers(1);

    FileExporter::macro('asNamedReport', function (string $name): FileExporter {
        /** @var FileExporter $this */
        return $this->filename($name)->withSerialNumber();
    });

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->asNamedReport('macro-report')
        ->store();

    expect($result->filename)->toBe('macro-report.xlsx');
});
