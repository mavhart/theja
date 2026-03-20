<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('pos_id')->nullable()->constrained('points_of_sale')->nullOnDelete();
            $table->string('feature_key');
            $table->integer('quantity')->default(1);
            $table->string('stripe_item_id')->nullable();
            $table->decimal('unit_price', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'feature_key', 'is_active']);
            $table->index('stripe_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_add_ons');
    }
};
