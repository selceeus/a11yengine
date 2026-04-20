<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdf_document_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('severity');
            $table->string('wcag_criteria')->nullable();
            $table->text('description');
            $table->text('element_context')->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_violations');
    }
};
