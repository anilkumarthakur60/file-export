<?php

declare(strict_types=1);

use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Mail\ExportReadyMail;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Storage;

function mailFor(string $path, int $threshold, ?string $url): ExportReadyMail
{
    $filename = basename($path);

    return new ExportReadyMail(
        result: new ExportResult(filename: $filename, url: $url, path: $path, disk: 'local'),
        disk: 'local',
        path: $path,
        attachThreshold: $threshold,
        mailSubject: 'Your export is ready',
    );
}

it('attaches files at or below the threshold', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('exports/small.csv', 'a,b,c');

    $mail = mailFor('exports/small.csv', 1024, null);

    $attachments = $mail->attachments();
    $this->assertCount(1, $attachments);
    $this->assertEquals(
        Attachment::fromStorageDisk('local', 'exports/small.csv')->as('small.csv'),
        $attachments[0],
    );

    $mail->assertSeeInHtml('small.csv');
    $mail->assertSeeInHtml('attached to this email');
});

it('links to files above the threshold', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('exports/big.csv', str_repeat('x', 5000));

    $mail = mailFor('exports/big.csv', 1024, 'https://files.example.com/big.csv');

    $this->assertCount(0, $mail->attachments());
    $mail->assertSeeInHtml('https://files.example.com/big.csv');
    $mail->assertSeeInHtml('Download file');
    $mail->assertSeeInHtml('big.csv');
});
