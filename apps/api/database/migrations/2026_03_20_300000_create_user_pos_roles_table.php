<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pos_roles', function (Blueprint $table) {
            // user_id è bigint per compatibilità con users.id (auto-increment)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('pos_id')->constrained('points_of_sale')->cascadeOnDelete();
            // role_id è bigint: Spatie roles.id è bigIncrements
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            // Override per-utente/per-POS: mostra prezzi d'acquisto
            $table->boolean('can_see_purchase_prices')->default(false);

            $table->primary(['user_id', 'pos_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pos_roles');
    }
};
