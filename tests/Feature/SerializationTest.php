<?php

declare(strict_types=1);

use Anil\FileExport\Engine\ExportDocument;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exports\CollectionExport;
use Anil\FileExport\Exports\QueryExport;
use Anil\FileExport\Jobs\PerformExport;
use Anil\FileExport\Tests\Fixtures\User;

it('serializes a queued collection export job', function (): void {
    $sheet = new CollectionExport(collect([['n' => 1]]), ['N'], fn (array $r) => [$r['n']]);
    $job = new PerformExport(
        new ExportDocument([$sheet]),
        'local',
        'exports/x.csv',
        'x.csv',
        ExportFormat::CSV,
        true,
        null,
        null,
        null,
        [],
    );

    /** @var PerformExport $restored */
    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(PerformExport::class)
        ->and($restored->filename)->toBe('x.csv')
        ->and($restored->format)->toBe(ExportFormat::CSV);
});

it('serializes a queued query export job built from a factory closure', function (): void {
    $sheet = new QueryExport(fn () => User::query(), ['Name'], fn (User $u) => [$u->name]);
    $job = new PerformExport(
        new ExportDocument([$sheet]),
        'local',
        'exports/users.xlsx',
        'users.xlsx',
        ExportFormat::XLSX,
        true,
        null,
        null,
        null,
        ['ops@example.com'],
    );

    /** @var PerformExport $restored */
    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(PerformExport::class)
        ->and($restored->mailRecipients)->toBe(['ops@example.com'])
        ->and($restored->document->firstSheet())->toBeInstanceOf(QueryExport::class);
});
