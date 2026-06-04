<?php

declare(strict_types=1);

namespace Anil\FileExport\Jobs;

use Anil\FileExport\Contracts\UrlResolver;
use Anil\FileExport\DataTransferObjects\CompletionConfig;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Engine\Contracts\ExportEngine;
use Anil\FileExport\Engine\ExportDocument;
use Anil\FileExport\Enums\ExportFormat;
use Anil\FileExport\Exceptions\FileExportException;
use Anil\FileExport\Services\ExportCompletionHandler;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Renders an export and writes it to disk on the queue, then runs the
 * completion actions (event / notification / callback / email) inline.
 *
 * One job does the whole job — there is no chained follow-up. All constructor
 * data is queue-serializable: the document's sheets wrap their closures, and
 * the completion callback is wrapped in a SerializableClosure.
 */
final class PerformExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $mailRecipients
     */
    public function __construct(
        public readonly ExportDocument $document,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $filename,
        public readonly ExportFormat $format,
        public readonly bool $fireEvent,
        public readonly ?object $notifiable,
        public readonly ?Notification $notification,
        public readonly ?SerializableClosure $callback,
        public readonly array $mailRecipients,
        public readonly ?string $mailSubject = null,
        public readonly ?int $mailAttachThreshold = null,
        public readonly ?int $expiresInDays = null,
    ) {}

    /**
     * @throws FileExportException
     */
    public function handle(ExportEngine $engine, ExportCompletionHandler $handler, UrlResolver $urlResolver): void
    {
        if (! $engine->store($this->document, $this->disk, $this->path, $this->format)) {
            throw FileExportException::storageFailed($this->filename);
        }

        $result = new ExportResult(
            filename: $this->filename,
            url: $urlResolver->resolve($this->disk, $this->path, $this->expiresInDays),
            path: $this->path,
            disk: $this->disk,
            format: $this->format->value,
            mimeType: $this->format->mimeType(),
        );

        $callback = $this->callback?->getClosure();

        $handler->handle($result, new CompletionConfig(
            fireEvent: $this->fireEvent,
            notifiable: $this->notifiable,
            notification: $this->notification,
            callback: $callback instanceof Closure ? $callback : null,
            mailRecipients: $this->mailRecipients,
            mailSubject: $this->mailSubject,
            mailAttachThreshold: $this->mailAttachThreshold,
        ));
    }
}
