<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // user_id è bigint per compatibilità con users.id (auto-increment)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('pos_id')->constrained('points_of_sale')->cascadeOnDelete();
            // Riferimento al token Sanctum per associare sessione ↔ token
            $table->foreignId('sanctum_token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();
            $table->string('device_fingerprint');
            $table->string('device_name');
            $table->enum('platform', ['web', 'pwa'])->default('web');
            $table->string('ip_address', 45)->nullable(); // IPv6 max 45 chars
            $table->timestamp('last_active_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'pos_id', 'platform', 'is_active']);
            $table->index('sanctum_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
