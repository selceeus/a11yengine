<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_webhook_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('platform');
            $table->text('webhook_url');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'category', 'platform', 'webhook_url'], 'notif_webhook_routes_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_webhook_routes');
    }
};
