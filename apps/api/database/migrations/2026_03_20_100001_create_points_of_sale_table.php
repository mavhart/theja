<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_sale', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Anagrafica POS
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('fiscal_code', 16)->nullable()->comment('Codice fiscale del punto vendita');
            $table->string('vat_number', 20)->nullable()->comment('Partita IVA del punto vendita');

            // Configurazione operativa
            $table->boolean('has_local_manager')->default(true);

            // ─── Feature flags (colonne dirette, non tabelle separate — da THEJA_MASTER.md §7)
            $table->boolean('has_virtual_cash_register')->default(false)
                ->comment('Add-on cassa virtuale RT Software (+€15/mese)');
            $table->boolean('cash_register_hardware_configured')->default(false)
                ->comment('RT fisico configurato per questo POS');
            $table->boolean('ai_analysis_enabled')->default(false)
                ->comment('Add-on AI Analysis (+€2/mese)');

            // ─── Limiti sessioni (leva commerciale — da THEJA_MASTER.md §5)
            $table->unsignedSmallInteger('max_concurrent_web_sessions')->default(1)
                ->comment('1 inclusa, +1 = €10/mese, illimitate = €17/mese');
            $table->unsignedSmallInteger('max_mobile_devices')->default(0)
                ->comment('0 = nessun PWA; +1 = €8/mese, +2 = €13, illimitati = €18');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_sale');
    }
};
