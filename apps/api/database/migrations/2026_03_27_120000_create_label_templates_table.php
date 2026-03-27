<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->string('name');
            $table->string('paper_format', 8)->default('A4');
            $table->decimal('label_width_mm', 5, 1);
            $table->decimal('label_height_mm', 5, 1);
            $table->integer('cols');
            $table->integer('rows');
            $table->decimal('margin_top_mm', 5, 1)->default(10);
            $table->decimal('margin_left_mm', 5, 1)->default(5);
            $table->decimal('spacing_h_mm', 4, 1)->default(2.5);
            $table->decimal('spacing_v_mm', 4, 1)->default(0);
            $table->jsonb('fields');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('pos_id')->references('id')->on('points_of_sale')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->index(['organization_id', 'pos_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
