<?php

use App\Support\TenantClinicalSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rimuove FK verso patients/prescriptions su sales e orders (se presenti da deploy precedenti).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = TenantClinicalSchema::schemaNameForOrganizationId((string) $id);
            $hasSales = DB::selectOne(
                'SELECT 1 AS x FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$schema, 'sales']
            );
            $hasOrders = DB::selectOne(
                'SELECT 1 AS x FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$schema, 'orders']
            );
            if ($hasSales) {
                DB::statement("ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_patient_id_foreign");
                DB::statement("ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_prescription_id_foreign");
            }
            if ($hasOrders) {
                DB::statement("ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_patient_id_foreign");
                DB::statement("ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_prescription_id_foreign");
            }
        }
    }

    public function down(): void
    {
        // Ripristino FK opzionale: non eseguito — vedi TenantClinicalSchema::addSalesForeignKeys / addOrdersForeignKeys
    }
};
