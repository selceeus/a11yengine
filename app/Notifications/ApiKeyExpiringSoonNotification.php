<?php

namespace App\Notifications;

use App\Models\ApiKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiKeyExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ApiKey $apiKey) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'api_key_id' => $this->apiKey->id,
            'api_key_name' => $this->apiKey->name,
            'key_prefix' => $this->apiKey->key_prefix,
            'expires_at' => $this->apiKey->expires_at->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = (int) now()->diffInDays($this->apiKey->expires_at);

        return (new MailMessage)
            ->subject("API key expiring soon — {$this->apiKey->name}")
            ->greeting('API Key Expiry Warning')
            ->line("The API key **{$this->apiKey->name}** (`{$this->apiKey->key_prefix}`) will expire in **{$daysLeft} day(s)** on **{$this->apiKey->expires_at->format('F j, Y')}**.")
            ->line('Please rotate this key before it expires to avoid service disruption.')
            ->action('Manage API Keys', route('api-keys.index'));
    }
}
