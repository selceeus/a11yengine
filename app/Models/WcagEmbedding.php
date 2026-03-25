<?php

namespace App\Models;

use App\Casts\VectorCast;
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
            'embedding' => VectorCast::class,
            'metadata' => 'array',
        ];
    }
}
