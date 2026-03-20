<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('vat_number', 20)->nullable()->comment('Partita IVA');
            $table->string('billing_email')->nullable();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Aggiunge FK da users.organization_id dopo che organizations esiste
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::dropIfExists('organizations');
    }
};
