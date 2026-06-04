<?php

declare(strict_types=1);

use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Events\ExportCompleted;
use Anil\FileExport\Jobs\HandleExportCompletion;
use Anil\FileExport\Mail\ExportReadyMail;
use Anil\FileExport\Tests\Fixtures\ExportDoneNotification;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Jobs\QueueExport;

it('fires the completion event on store', function (): void {
    Excel::fake();
    Event::fake([ExportCompleted::class]);
    seedUsers(2);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->store();

    Event::assertDispatched(ExportCompleted::class, function (ExportCompleted $event): bool {
        return $event->result->filename === 'export.xlsx';
    });
});

it('can disable the completion event', function (): void {
    Excel::fake();
    Event::fake([ExportCompleted::class]);
    seedUsers(1);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->withCompletionEvent(false)
        ->store();

    Event::assertNotDispatched(ExportCompleted::class);
});

it('emails the export on store', function (): void {
    Excel::fake();
    Mail::fake();
    seedUsers(2);

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->notifyMail('boss@example.com')
        ->store();

    Mail::assertSent(ExportReadyMail::class, function (ExportReadyMail $mail): bool {
        return $mail->hasTo('boss@example.com');
    });
});

it('sends a notification on store', function (): void {
    Excel::fake();
    Notification::fake();
    seedUsers(1);
    $user = User::query()->firstOrFail();

    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->notify($user, new ExportDoneNotification)
        ->store();

    Notification::assertSentTo($user, ExportDoneNotification::class);
});

it('runs a completion callback on store', function (): void {
    Excel::fake();
    seedUsers(1);

    $captured = null;
    fileExporter()
        ->fromQuery(User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->onCompletion(function (ExportResult $result) use (&$captured): void {
            $captured = $result;
        })
        ->store();

    $this->assertInstanceOf(ExportResult::class, $captured);
    $this->assertSame('export.xlsx', $captured->filename);
});

it('appends the completion job to the queue chain', function (): void {
    Queue::fake();
    seedUsers(2);

    // A factory closure keeps the query serializable for the queue.
    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->notifyMail('boss@example.com')
        ->queue();

    Queue::assertPushed(QueueExport::class, function (QueueExport $job): bool {
        return collect($job->chained)->contains(
            fn ($chained) => str_contains((string) $chained, class_basename(HandleExportCompletion::class))
        );
    });
});
