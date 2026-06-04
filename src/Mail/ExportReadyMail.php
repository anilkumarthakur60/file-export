<?php

declare(strict_types=1);

namespace Anil\FileExport\Mail;

use Anil\FileExport\DataTransferObjects\ExportResult;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Emails a finished export to its recipients.
 *
 * Delivery adapts to file size: files at or below the configured threshold
 * are attached directly; larger files are delivered as a download link
 * (signed for cloud disks) so the message stays small.
 *
 * The body view is publishable (resources/views/vendor/file-export) for
 * customization; see the file-export-views publish tag.
 */
final class ExportReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  ExportResult  $result  The finished export (filename + download URL).
     * @param  string  $disk  The disk the file was stored on.
     * @param  string  $path  The file path relative to the disk root.
     * @param  int  $attachThreshold  Max file size in bytes to attach; larger files link instead.
     * @param  string  $mailSubject  The email subject line.
     */
    public function __construct(
        public readonly ExportResult $result,
        public readonly string $disk,
        public readonly string $path,
        public readonly int $attachThreshold,
        public readonly string $mailSubject,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'file-export::emails.export-ready',
            with: [
                'filename' => $this->result->filename,
                'url' => $this->shouldAttach() ? null : $this->result->url,
                'subject' => $this->mailSubject,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->shouldAttach()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($this->disk, $this->path)->as($this->result->filename),
        ];
    }

    /**
     * Whether the file is small enough to attach directly.
     */
    private function shouldAttach(): bool
    {
        return $this->fileSize() <= $this->attachThreshold;
    }

    /**
     * Resolve the stored file size in bytes (PHP_INT_MAX if missing, forcing a link).
     */
    private function fileSize(): int
    {
        $storage = Storage::disk($this->disk);

        return $storage->exists($this->path) ? $storage->size($this->path) : PHP_INT_MAX;
    }
}
