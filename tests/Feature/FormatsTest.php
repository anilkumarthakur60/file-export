<?php

declare(strict_types=1);

use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Storage;

function exportInFormat(ExportFormat $format): string
{
    Storage::fake('local');
    seedUsers(2);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name', 'Email'])
        ->withMapping(fn (User $u) => [$u->name, $u->email])
        ->format($format)
        ->store();

    return (string) Storage::disk('local')->get('exports/export.'.$format->extension());
}

it('renders a valid xlsx (zip container)', function (): void {
    expect(substr(exportInFormat(ExportFormat::XLSX), 0, 2))->toBe('PK');
});

it('renders a valid ods (zip container)', function (): void {
    expect(substr(exportInFormat(ExportFormat::ODS), 0, 2))->toBe('PK');
});

it('renders an html table', function (): void {
    $html = exportInFormat(ExportFormat::HTML);
    expect($html)->toContain('<table')
        ->and($html)->toContain('User 1');
});

it('renders a tsv with tab delimiter', function (): void {
    $tsv = exportInFormat(ExportFormat::TSV);
    expect($tsv)->toContain("\"Name\"\t\"Email\"");
});

it('renders a pdf via the detected driver', function (): void {
    $pdf = exportInFormat(ExportFormat::PDF);
    expect(substr($pdf, 0, 4))->toBe('%PDF');
});
