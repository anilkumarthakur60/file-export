<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

use Closure;
use Illuminate\Notifications\Notification;

/**
 * The set of "what to do when the export finishes" options for one export.
 *
 * Populated from the fluent completion methods on FileExporter (notify,
 * notifyMail, onCompletion, withCompletionEvent) and consumed by
 * ExportCompletionHandler on both the sync and queued paths.
 */
final readonly class CompletionConfig
{
    /**
     * @param  bool  $fireEvent  Whether to dispatch the ExportCompleted event.
     * @param  object|null  $notifiable  The notifiable to send $notification to (typically an Eloquent model).
     * @param  Notification|null  $notification  The notification to send on completion.
     * @param  Closure|null  $callback  A callback invoked with the ExportResult on completion.
     * @param  array<int, string>  $mailRecipients  Email addresses that receive the export (attachment or link).
     */
    public function __construct(
        public bool $fireEvent = true,
        public ?object $notifiable = null,
        public ?Notification $notification = null,
        public ?Closure $callback = null,
        public array $mailRecipients = [],
    ) {}

    /**
     * Whether any completion action is configured (beyond the default event).
     */
    public function hasWork(): bool
    {
        return $this->fireEvent
            || ($this->notifiable !== null && $this->notification !== null)
            || $this->callback !== null
            || $this->mailRecipients !== [];
    }
}
