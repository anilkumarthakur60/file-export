<?php

declare(strict_types=1);

use Anil\FileExport\Import\Contracts\BatchesInserts;
use Anil\FileExport\Import\Contracts\MapsCells;
use Anil\FileExport\Import\Contracts\MapsToModel;
use Anil\FileExport\Import\Contracts\SkipsOnFailure;
use Anil\FileExport\Import\Contracts\Upserts;
use Anil\FileExport\Import\Contracts\UsesHeadingRow;
use Anil\FileExport\Import\Contracts\ValidatesRows;
use Anil\FileExport\Import\Failure;
use Anil\FileExport\Services\FileImporter;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

function csvFile(string $contents): string
{
    Storage::fake('local');
    Storage::disk('local')->put('in.csv', $contents);

    return Storage::disk('local')->path('in.csv');
}

function importFile(): FileImporter
{
    return app(FileImporter::class);
}

it('batch-inserts mapped models', function (): void {
    $path = csvFile("name,email\nA,a@x.com\nB,b@x.com\nC,c@x.com\n");

    $importable = new class implements MapsToModel, UsesHeadingRow, BatchesInserts
    {
        public function headingRow(): int
        {
            return 1;
        }

        public function batchSize(): int
        {
            return 2;
        }

        public function model(array $row): Model|array|null
        {
            return new User(['name' => $row['name'], 'email' => $row['email'], 'created_at' => now(), 'updated_at' => now()]);
        }
    };

    importFile()->from($path)->import($importable);

    expect(User::query()->count())->toBe(3)
        ->and(User::query()->pluck('name')->all())->toContain('A', 'B', 'C');
});

it('upserts mapped models by a unique key', function (): void {
    User::query()->create(['name' => 'Old', 'email' => 'dup@x.com']);

    $path = csvFile("name,email\nNew,dup@x.com\n");

    $importable = new class implements MapsToModel, UsesHeadingRow, Upserts
    {
        public function headingRow(): int
        {
            return 1;
        }

        public function uniqueBy(): string|array
        {
            return 'email';
        }

        public function model(array $row): Model|array|null
        {
            return new User(['name' => $row['name'], 'email' => $row['email']]);
        }
    };

    importFile()->from($path)->import($importable);

    expect(User::query()->where('email', 'dup@x.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'dup@x.com')->first()->name)->toBe('New');
});

it('skips rows that fail validation', function (): void {
    $path = csvFile("name,email\nGood,good@x.com\nBad,not-an-email\n");

    $failures = [];
    $importable = new class($failures) implements MapsToModel, UsesHeadingRow, ValidatesRows, SkipsOnFailure
    {
        /** @param array<int, Failure> $failures */
        public function __construct(public array &$failures) {}

        public function headingRow(): int
        {
            return 1;
        }

        public function rules(): array
        {
            return ['email' => 'email'];
        }

        public function onFailure(array $failures): void
        {
            foreach ($failures as $failure) {
                $this->failures[] = $failure;
            }
        }

        public function model(array $row): Model|array|null
        {
            return new User(['name' => $row['name'], 'email' => $row['email']]);
        }
    };

    importFile()->from($path)->import($importable);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->first()->email)->toBe('good@x.com')
        ->and($importable->failures)->toHaveCount(1);
});

it('reads specific mapped cells', function (): void {
    $path = csvFile("ignored,Acme Corp\nfoo,bar\n");

    $captured = [];
    $importable = new class($captured) implements MapsCells
    {
        /** @param array<int|string, mixed> $captured */
        public function __construct(public array &$captured) {}

        public function cellMap(): array
        {
            return ['company' => 'B1'];
        }
    };

    // MapsCells feeds collection/array consumers; capture via a closure-backed array.
    $importable = new class($captured) implements MapsCells, \Anil\FileExport\Import\Contracts\ReceivesArray
    {
        /** @param array<int, array<int|string, mixed>> $captured */
        public function __construct(public array &$captured) {}

        public function cellMap(): array
        {
            return ['company' => 'B1'];
        }

        public function array(array $rows): void
        {
            $this->captured = $rows;
        }
    };

    importFile()->from($path)->import($importable);

    expect($importable->captured[0])->toBe(['company' => 'Acme Corp']);
});
