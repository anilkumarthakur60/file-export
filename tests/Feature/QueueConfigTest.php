<?php

declare(strict_types=1);

use Anil\FileExport\Tests\Fixtures\User;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Jobs\QueueExport;

it('dispatches on the overridden connection and queue', function (): void {
    Queue::fake();
    User::query()->create(['name' => 'A', 'email' => 'a@example.com']);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->onConnection('redis')
        ->onQueue('exports')
        ->queue();

    Queue::assertPushed(QueueExport::class, function (QueueExport $job): bool {
        return $job->connection === 'redis' && $job->queue === 'exports';
    });
});

it('uses the configured queue by default', function (): void {
    config()->set('file-export.queue.queue', 'configured-exports');
    Queue::fake();
    User::query()->create(['name' => 'A', 'email' => 'a@example.com']);

    fileExporter()
        ->fromQuery(fn () => User::query())
        ->withHeaders(['Name'])
        ->withMapping(fn (User $u) => [$u->name])
        ->queue();

    Queue::assertPushed(QueueExport::class, function (QueueExport $job): bool {
        return $job->queue === 'configured-exports';
    });
});
