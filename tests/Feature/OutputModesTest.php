<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

it('stores a query export to disk', function (): void {
    Excel::fake();
    seedUsers(3);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->store();

    Excel::assertStored('exports/export.xlsx', 'local');
    $this->assertSame('export.xlsx', $result->filename);
    $this->assertSame('exports/export.xlsx', $result->path);
    $this->assertSame('local', $result->disk);
});

it('downloads an export', function (): void {
    Excel::fake();
    seedUsers(2);

    fileExporter()
        ->fromCollection(new Collection([['n' => 'A'], ['n' => 'B']]))
        ->withHeaders(['Name'])
        ->withMapping(fn (array $row) => [$row['n']])
        ->filename('people')
        ->format(ExportFormat::CSV)
        ->download();

    Excel::assertDownloaded('people.csv');
});

it('queues an export', function (): void {
    Excel::fake();
    seedUsers(2);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->queue();

    Excel::assertQueued('exports/export.xlsx', 'local');
});

it('rejects queueing a live query builder', function (): void {
    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('factory closure');

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->queue();
});

it('returns raw binary content', function (): void {
    seedUsers(1);

    $raw = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->format(ExportFormat::CSV)
        ->raw();

    $this->assertIsString($raw);
    $this->assertStringContainsString('User 1', $raw);
});

it('throws when no data source', function (): void {
    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('No data source');

    fileExporter()
        ->withHeaders(['Name'])
        ->withMapping(fn ($r) => [$r])
        ->raw();
});

it('throws when no headers', function (): void {
    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('No headers');

    fileExporter()
        ->fromQuery(User::query())
        ->withMapping(fn (User $u) => [$u->name])
        ->raw();
});

it('throws when no mapping', function (): void {
    $this->expectException(FileExportException::class);
    $this->expectExceptionMessage('No mapping');

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->raw();
});
