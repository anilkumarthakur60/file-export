<?php

declare(strict_types=1);

use Anil\FileExport\DataTransferObjects\ExportResult;
use Anil\FileExport\Events\ExportCompleted;
use Anil\FileExport\Jobs\PerformExport;
use Anil\FileExport\Mail\ExportReadyMail;
use Anil\FileExport\Tests\Fixtures\ExportDoneNotification;
use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('fires the completion event on store', function (): void {
    Storage::fake('local');
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
    Storage::fake('local');
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
    Storage::fake('local');
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
    Storage::fake('local');
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
    Storage::fake('local');
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

    expect($captured)->toBeInstanceOf(ExportResult::class)
        ->and($captured->filename)->toBe('export.xlsx');
});

it('carries the completion config on the queued job', function (): void {
    Queue::fake();
    seedUsers(2);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->notifyMail('boss@example.com')
        ->queue();

    Queue::assertPushed(PerformExport::class, function (PerformExport $job): bool {
        return $job->mailRecipients === ['boss@example.com'];
    });
});
