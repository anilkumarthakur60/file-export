<?php

declare(strict_types=1);

namespace Anil\FileExport\Tests\Fixtures;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Test notification dispatched on export completion.
 */
class ExportDoneNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Export done')
            ->line('Your export is ready.');
    }
}
