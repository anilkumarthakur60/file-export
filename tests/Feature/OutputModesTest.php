<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Jobs\PerformExport;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('stores a query export to disk', function (): void {
    Storage::fake('local');
    seedUsers(3);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->store();

    Storage::disk('local')->assertExists('exports/export.xlsx');
    expect($result->filename)->toBe('export.xlsx')
        ->and($result->path)->toBe('exports/export.xlsx')
        ->and($result->disk)->toBe('local')
        ->and($result->format)->toBe('xlsx');
});

it('downloads an export', function (): void {
    seedUsers(2);

    $response = fileExporter()
        ->fromCollection(new Collection([['n' => 'A'], ['n' => 'B']]))
        ->withHeaders(['Name'])
        ->withMapping(fn (array $row) => [$row['n']])
        ->filename('people')
        ->format(ExportFormat::CSV)
        ->download();

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-disposition'))->toContain('people.csv')
        ->and($response->headers->get('content-type'))->toContain('text/csv');
});

it('queues an export', function (): void {
    Queue::fake();
    seedUsers(2);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->queue();

    Queue::assertPushed(PerformExport::class);
});

it('runs a queued export end to end on the sync queue', function (): void {
    Storage::fake('local');
    seedUsers(3);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->queue();

    // QUEUE_CONNECTION=sync runs PerformExport inline: the file is rendered and stored.
    Storage::disk('local')->assertExists('exports/export.xlsx');
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

    expect($raw)->toBeString()
        ->and($raw)->toContain('User 1');
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
