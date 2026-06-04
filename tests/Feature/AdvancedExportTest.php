<?php

declare(strict_types=1);

use Anil\FileExport\Events\SheetWritten;
use Anil\FileExport\Events\WritingExport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

function loadSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    return IOFactory::load(Storage::disk('local')->path('exports/export.xlsx'))->getActiveSheet();
}

it('exports from a plain array', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromArray([['Alice', 1], ['Bob', 2]])
        ->withHeaders(['Name', 'Score'])
        ->withMapping(fn (array $r) => [$r[0], $r[1]])
        ->store();

    $sheet = loadSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Name')
        ->and($sheet->getCell('A2')->getValue())->toBe('Alice')
        ->and((int) $sheet->getCell('B3')->getValue())->toBe(2);
});

it('exports from a generator', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromGenerator(function () {
            yield 'X';
            yield 'Y';
        })
        ->withHeaders(['Letter'])
        ->withMapping(fn (string $l) => [$l])
        ->store();

    $sheet = loadSheet();
    expect($sheet->getCell('A2')->getValue())->toBe('X')
        ->and($sheet->getCell('A3')->getValue())->toBe('Y');
});

it('exports from an iterator', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromIterator(new ArrayIterator([['a'], ['b']]))
        ->withHeaders(['Col'])
        ->withMapping(fn (array $r) => [$r[0]])
        ->store();

    expect(loadSheet()->getCell('A2')->getValue())->toBe('a');
});

it('exports from a blade view', function (): void {
    view()->addLocation(__DIR__.'/../Fixtures/views');
    Storage::fake('local');

    fileExporter()
        ->fromView(fn () => view('report', ['names' => ['Alice', 'Bob']]))
        ->store();

    $sheet = loadSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Name')
        ->and($sheet->getCell('A2')->getValue())->toBe('Alice')
        ->and($sheet->getCell('A3')->getValue())->toBe('Bob');
});

it('runs a style callback against the worksheet', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromArray([['A']])
        ->withHeaders(['Col'])
        ->withMapping(fn (array $r) => [$r[0]])
        ->styleUsing(fn ($ws) => $ws->setCellValue('Z1', 'touched'))
        ->store();

    expect(loadSheet()->getCell('Z1')->getValue())->toBe('touched');
});

it('honors a custom start cell', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromArray([['A']])
        ->withHeaders(['Col'])
        ->withMapping(fn (array $r) => [$r[0]])
        ->startCell('B2')
        ->store();

    $sheet = loadSheet();
    expect($sheet->getCell('B2')->getValue())->toBe('Col')
        ->and($sheet->getCell('B3')->getValue())->toBe('A');
});

it('fires export events', function (): void {
    Event::fake([WritingExport::class, SheetWritten::class]);
    Storage::fake('local');

    fileExporter()
        ->fromArray([['A']])
        ->withHeaders(['Col'])
        ->withMapping(fn (array $r) => [$r[0]])
        ->store();

    Event::assertDispatched(WritingExport::class);
    Event::assertDispatched(SheetWritten::class);
});

it('applies a default background colour without error', function (): void {
    Storage::fake('local');

    fileExporter()
        ->fromArray([['A']])
        ->withHeaders(['Col'])
        ->withMapping(fn (array $r) => [$r[0]])
        ->background('FFFF00')
        ->store();

    Storage::disk('local')->assertExists('exports/export.xlsx');
});
