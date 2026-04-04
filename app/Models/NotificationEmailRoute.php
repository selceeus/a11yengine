<?php

namespace App\Models;

use App\Enums\NotificationEmailCategory;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEmailRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'category',
        'email',
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
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return array<int, string>
     */
    public static function getEmailsForCategory(Agency|int $agency, string $category): array
    {
        $agencyId = $agency instanceof Agency ? $agency->id : $agency;

        return self::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $agencyId)
            ->where('category', $category)
            ->pluck('email')
            ->all();
    }
}
