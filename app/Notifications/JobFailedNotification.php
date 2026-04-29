<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Events\JobFailed;

class JobFailedNotification extends Notification
{
    public function __construct(public readonly JobFailed $event) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job' => $this->event->job->getName(),
            'exception' => $this->event->exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jobName = class_basename($this->event->job->getName());
        $message = $this->event->exception->getMessage();

        return (new MailMessage)
            ->subject("Queued job failed: {$jobName}")
            ->error()
            ->greeting('Job Failed')
            ->line("The queued job **{$jobName}** has exhausted all retry attempts.")
            ->line('Error: '.mb_substr($message, 0, 500))
            ->line('Please check the failed jobs table and your application logs.');
    }
}
