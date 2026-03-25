<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'wcag_embeddings',
        'lawsuit_embeddings',
        'remediation_embeddings',
    ];

    private const DIMENSIONS = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        foreach (self::TABLES as $table) {
            // Convert jsonb → vector(1536), casting existing data
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN embedding TYPE vector(%d) USING embedding::text::vector(%d)',
                $table,
                self::DIMENSIONS,
                self::DIMENSIONS,
            ));

            // Add HNSW index for fast cosine similarity search
            DB::statement(sprintf(
                'CREATE INDEX %s_embedding_idx ON %s USING hnsw (embedding vector_cosine_ops)',
                $table,
                $table,
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s_embedding_idx', $table));

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN embedding TYPE jsonb USING embedding::text::jsonb',
                $table,
            ));
        }
    }
};
