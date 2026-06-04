<?php

declare(strict_types=1);

use Anil\FileExport\Mail\ExportReadyMail;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('overrides the storage path at runtime', function (): void {
    Storage::fake('local');
    seedUsers(1);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->path('reports/2026')
        ->store();

    Storage::disk('local')->assertExists('reports/2026/export.xlsx');
    expect($result->path)->toBe('reports/2026/export.xlsx');
});

it('overrides max_rows at runtime to raise the guard', function (): void {
    config()->set('file-export.max_rows', 3);
    Storage::fake('local');
    seedUsers(10);

    // Config would throw at 3 rows, but the runtime override raises it.
    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(false)
        ->maxRows(100)
        ->store();

    expect($result->filename)->toBe('export.xlsx');
});

it('overrides max_rows at runtime to unlimited', function (): void {
    config()->set('file-export.max_rows', 3);
    Storage::fake('local');
    seedUsers(10);

    $result = fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->autoSplit(false)
        ->maxRows(null)
        ->store();

    expect($result->filename)->toBe('export.xlsx');
});

it('overrides the email subject at runtime', function (): void {
    Storage::fake('local');
    Mail::fake();
    seedUsers(1);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->notifyMail('boss@example.com')
        ->mailSubject('Custom Runtime Subject')
        ->store();

    Mail::assertSent(ExportReadyMail::class, function (ExportReadyMail $mail): bool {
        return $mail->mailSubject === 'Custom Runtime Subject';
    });
});
