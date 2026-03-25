<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WcagEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'criterion',
        'chunk_index',
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
