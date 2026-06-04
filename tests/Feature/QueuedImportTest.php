<?php

declare(strict_types=1);

use Anil\FileExport\Events\ImportRead;
use Anil\FileExport\Events\ReadingImport;
use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\ReadsChunked;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Anil\FileExport\Jobs\ImportFile;
use Anil\FileExport\Jobs\ReadImportChunk;
use Anil\FileExport\Services\FileImporter;
use Anil\FileExport\Tests\Fixtures\User;
use Anil\FileExport\Tests\Fixtures\UserImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function csvSource(string $contents): string
{
    Storage::fake('local');
    Storage::disk('local')->put('in.csv', $contents);

    return Storage::disk('local')->path('in.csv');
}

it('queues a non-chunked import as a single job', function (): void {
    Queue::fake();
    $path = csvSource("name,email\nA,a@x.com\n");

    app(FileImporter::class)->from($path)->queue(new UserImport);

    Queue::assertPushed(ImportFile::class, fn (ImportFile $job) => $job->importable instanceof UserImport);
});

it('splits a chunked import into windowed jobs', function (): void {
    Queue::fake();
    $path = csvSource("name,email\nA,a@x.com\nB,b@x.com\nC,c@x.com\nD,d@x.com\n");

    $importable = new class implements MapsToModel, UsesHeadingRow, ReadsChunked
    {
        public function headingRow(): int
        {
            return 1;
        }

        public function chunkSize(): int
        {
            return 2;
        }

        public function model(array $row): Model|array|null
        {
            return new User(['name' => $row['name'], 'email' => $row['email']]);
        }
    };

    app(FileImporter::class)->from($path)->queue($importable);

    // 4 data rows / chunk size 2 = 2 chunk jobs.
    Queue::assertPushed(ReadImportChunk::class, 2);
});

it('runs a queued import end to end on the sync queue', function (): void {
    $path = csvSource("name,email\nA,a@x.com\nB,b@x.com\n");

    app(FileImporter::class)->from($path)->queue(new UserImport);

    expect(User::query()->count())->toBe(2);
});

it('fires import events', function (): void {
    Event::fake([ReadingImport::class, ImportRead::class]);
    $path = csvSource("name,email\nA,a@x.com\n");

    app(FileImporter::class)->from($path)->import(new UserImport);

    Event::assertDispatched(ReadingImport::class);
    Event::assertDispatched(ImportRead::class);
});
