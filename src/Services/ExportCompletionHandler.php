<?php

declare(strict_types=1);

namespace Anil\FileExport\Services;

use Anil\FileExport\DataTransferObjects\CompletionConfig;
use Anil\FileExport\DataTransferObjects\ExportConfig;
use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Events\ExportCompleted;
use Anil\FileExport\Mail\ExportReadyMail;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Runs the "after the file exists" actions for a finished export.
 *
 * Shared by both the synchronous store() path and the queued
 * HandleExportCompletion job, so the four completion mechanisms behave
 * identically regardless of how the export was produced:
 *   1. always fire the ExportCompleted event (unless disabled);
 *   2. send a Notification to a notifiable, if configured;
 *   3. invoke a user callback with the ExportResult, if configured;
 *   4. email the export (attachment or link), if recipients were given.
 *
 * The mail and notification facades are used deliberately so Mail::fake()
 * and Notification::fake() intercept them in tests.
 */
final class ExportCompletionHandler
{
    public function __construct(
        private readonly ExportConfig $config,
        private readonly Dispatcher $events,
    ) {}

    public function handle(ExportResult $result, CompletionConfig $completion): void
    {
        if ($completion->fireEvent) {
            $this->events->dispatch(new ExportCompleted($result));
        }

        if ($completion->notifiable !== null && $completion->notification !== null) {
            Notification::send($completion->notifiable, $completion->notification);
        }

        if ($completion->callback instanceof Closure) {
            ($completion->callback)($result);
        }

        if ($completion->mailRecipients !== [] && $result->disk !== null && $result->path !== null) {
            Mail::to($completion->mailRecipients)->send(new ExportReadyMail(
                result: $result,
                disk: $result->disk,
                path: $result->path,
                attachThreshold: $this->config->mailAttachThreshold,
                mailSubject: $this->config->mailSubject,
            ));
        }
    }
}
