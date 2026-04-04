<?php

namespace App\Models;

use App\Enums\MessagingPlatform;
use App\Enums\NotificationEmailCategory;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class NotificationWebhookRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'category',
        'platform',
        'webhook_url',
        'label',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected function casts(): array
    {
        return [
            'category' => NotificationEmailCategory::class,
            'platform' => MessagingPlatform::class,
        ];
    }

    public function getWebhookUrlAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function setWebhookUrlAttribute(string $value): void
    {
        $this->attributes['webhook_url'] = Crypt::encryptString($value);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return array<int, array{url: string, platform: MessagingPlatform}>
     */
    public static function getWebhooksForCategory(Agency|int $agency, string $category): array
    {
        $agencyId = $agency instanceof Agency ? $agency->id : $agency;

        return self::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $agencyId)
            ->where('category', $category)
            ->get(['platform', 'webhook_url'])
            ->map(fn (self $route) => [
                'url' => $route->webhook_url,
                'platform' => $route->platform,
            ])
            ->all();
    }
}
