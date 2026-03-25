<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast a pgvector `vector` column to/from a PHP float array.
 *
 * Database stores: '[0.1,0.2,0.3]' (vector type)
 * PHP gets: [0.1, 0.2, 0.3] (array of floats)
 *
 * @implements CastsAttributes<list<float>, list<float>>
 */
class VectorCast implements CastsAttributes
{
    /**
     * @return list<float>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        // pgvector returns '[0.1,0.2,0.3]' as a string
        if (is_string($value)) {
            $trimmed = trim($value, '[]');

            return $trimmed === '' ? [] : array_map('floatval', explode(',', $trimmed));
        }

        // Already an array (e.g. from factory/test)
        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        return [];
    }

    /**
     * @param  list<float>  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_string($value)) {
            return $value;
        }

        return '['.implode(',', array_map('strval', $value)).']';
    }
}
