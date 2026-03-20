<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('stripe_customer_id');
            $table->string('stripe_subscription_id')->nullable();
            $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'paused'])->default('trialing');
            $table->integer('plan_base_pos_count')->default(1);
            $table->decimal('monthly_total', 10, 2)->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('stripe_customer_id');
            $table->index('stripe_subscription_id');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
