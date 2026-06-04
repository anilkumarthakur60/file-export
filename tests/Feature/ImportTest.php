<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Import\Contracts\HandlesEachRow;
use Anil\FileExport\Import\Contracts\ReceivesArray;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Anil\FileExport\Import\ImportRow;
use Anil\FileExport\Services\FileExporter;
use Anil\FileExport\Services\FileImporter;
use Anil\FileExport\Tests\Fixtures\User;
use Anil\FileExport\Tests\Fixtures\UserImport;
use Illuminate\Support\Facades\Storage;

function importer(): FileImporter
{
    return app(FileImporter::class);
}

function writeUsersFile(string $format = 'xlsx'): string
{
    Storage::fake('local');
    seedUsers(3);

    app(FileExporter::class)
        ->fromQuery(User::query())
        ->withHeaders(['name', 'email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->format(ExportFormat::from($format))
        ->store();

    return Storage::disk('local')->path("exports/export.{$format}");
}

it('reads a file into an array', function (): void {
    $path = writeUsersFile();

    $sheets = importer()->from($path)->toArray();

    expect($sheets)->toHaveCount(1)
        ->and($sheets[0])->toHaveCount(4) // header + 3 rows
        ->and($sheets[0][0])->toBe(['name', 'email'])
        ->and($sheets[0][1][0])->toBe('User 1');
});

it('reads a file into a collection', function (): void {
    $path = writeUsersFile();

    $collection = importer()->from($path)->toCollection();

    expect($collection->first())->toHaveCount(4);
});

it('round-trips a query export back into models via an importable', function (): void {
    $path = writeUsersFile();

    // Fresh table.
    User::query()->delete();
    expect(User::query()->count())->toBe(0);

    importer()->from($path)->import(new UserImport);

    expect(User::query()->count())->toBe(3)
        ->and(User::query()->orderBy('id')->first()->name)->toBe('User 1');
});

it('maps rows by heading row', function (): void {
    $path = writeUsersFile();

    $captured = [];
    $importable = new class($captured) implements ReceivesArray, UsesHeadingRow
    {
        /** @param array<int, array<int|string, mixed>> $captured */
        public function __construct(public array &$captured) {}

        public function headingRow(): int
        {
            return 1;
        }

        public function array(array $rows): void
        {
            $this->captured = $rows;
        }
    };

    importer()->from($path)->import($importable);

    expect($importable->captured)->toHaveCount(3)
        ->and($importable->captured[0])->toBe(['name' => 'User 1', 'email' => 'user1@example.com']);
});

it('handles each row individually', function (): void {
    $path = writeUsersFile();

    $names = [];
    $importable = new class($names) implements HandlesEachRow, UsesHeadingRow
    {
        /** @param array<int, mixed> $names */
        public function __construct(public array &$names) {}

        public function headingRow(): int
        {
            return 1;
        }

        public function onRow(ImportRow $row): void
        {
            $this->names[] = $row->toArray()['name'];
        }
    };

    importer()->from($path)->import($importable);

    expect($importable->names)->toBe(['User 1', 'User 2', 'User 3']);
});

it('reads csv files too', function (): void {
    $path = writeUsersFile('csv');

    $sheets = importer()->from($path)->toArray();

    expect($sheets[0][0])->toBe(['name', 'email'])
        ->and($sheets[0][1][0])->toBe('User 1');
});
