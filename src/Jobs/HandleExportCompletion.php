<?php

declare(strict_types=1);

namespace Anil\FileExport\Jobs;

use Anil\FileExport\Contracts\UrlResolver;
use Anil\FileExport\DataTransferObjects\CompletionConfig;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Services\ExportCompletionHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Runs the completion actions after a queued export's write jobs finish.
 *
 * Appended to the Maatwebsite export chain so it executes only once the file
 * is fully written to disk. The download URL is resolved here (not at queue
 * time) so signed URLs are freshly minted. All constructor data is
 * queue-serializable: the user callback is wrapped in a SerializableClosure.
 */
final class HandleExportCompletion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $mailRecipients
     */
    public function __construct(
        private readonly string $disk,
        private readonly string $path,
        private readonly string $filename,
        private readonly bool $fireEvent,
        private readonly ?object $notifiable,
        private readonly ?Notification $notification,
        private readonly ?SerializableClosure $callback,
        private readonly array $mailRecipients,
    ) {}

    public function handle(ExportCompletionHandler $handler, UrlResolver $urlResolver): void
    {
        $result = new ExportResult(
            filename: $this->filename,
            url: $urlResolver->resolve($this->disk, $this->path),
            path: $this->path,
            disk: $this->disk,
        );

        $callback = $this->callback?->getClosure();

        $handler->handle($result, new CompletionConfig(
            fireEvent: $this->fireEvent,
            notifiable: $this->notifiable,
            notification: $this->notification,
            callback: $callback instanceof \Closure ? $callback : null,
            mailRecipients: $this->mailRecipients,
        ));
    }
}
