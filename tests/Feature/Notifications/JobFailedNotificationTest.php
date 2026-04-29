<?php

use App\Notifications\JobFailedNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;

function makeFakeJobFailedEvent(string $jobName, string $errorMessage): JobFailed
{
    $job = Mockery::mock(SyncJob::class);
    $job->shouldReceive('getName')->andReturn($jobName);
    $job->shouldReceive('payload')->andReturn(['data' => ['command' => '']]);

    return new JobFailed('sync', $job, new RuntimeException($errorMessage));
}

it('sends via mail channel', function (): void {
    $event = makeFakeJobFailedEvent('App\\Jobs\\RunScanJob', 'Something went wrong');
    $notification = new JobFailedNotification($event);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('includes the job class name in the mail subject', function (): void {
    $event = makeFakeJobFailedEvent('App\\Jobs\\RunScanJob', 'Something went wrong');
    $notification = new JobFailedNotification($event);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('RunScanJob');
});

it('includes the exception message in the mail body', function (): void {
    $event = makeFakeJobFailedEvent('App\\Jobs\\RunScanJob', 'OpenAI timeout');
    $notification = new JobFailedNotification($event);

    $mail = $notification->toMail(new stdClass);

    $lines = collect($mail->introLines);
    expect($lines->implode(' '))->toContain('OpenAI timeout');
});

it('truncates exception messages longer than 500 characters', function (): void {
    $longMessage = str_repeat('x', 600);
    $event = makeFakeJobFailedEvent('App\\Jobs\\RunScanJob', $longMessage);
    $notification = new JobFailedNotification($event);

    $mail = $notification->toMail(new stdClass);

    $lines = collect($mail->introLines)->implode(' ');
    expect(strlen($lines))->toBeLessThan(700);
});

it('includes job name and exception in toArray', function (): void {
    $event = makeFakeJobFailedEvent('App\\Jobs\\RunScanJob', 'Some error');
    $notification = new JobFailedNotification($event);

    $array = $notification->toArray(new stdClass);

    expect($array)->toHaveKey('job', 'App\\Jobs\\RunScanJob')
        ->toHaveKey('exception', 'Some error')
        ->toHaveKey('failed_at');
});
