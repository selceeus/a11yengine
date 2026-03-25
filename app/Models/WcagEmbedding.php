<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WcagEmbedding extends Model
{
    protected $fillable = [
        'criterion',
        'level',
        'title',
        'chunk',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }
}
