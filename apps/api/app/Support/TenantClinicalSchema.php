<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle cliniche negli schema tenant PostgreSQL (tenant_{orgIdNoHyphens}).
 */
class TenantClinicalSchema
{
    public static function schemaNameForOrganizationId(string $organizationId): string
    {
        return 'tenant_' . str_replace('-', '', $organizationId);
    }

    public static function provisionAllClinicalForOrganization(string $organizationId): void
    {
        $schema = self::schemaNameForOrganizationId($organizationId);
        DB::statement('CREATE SCHEMA IF NOT EXISTS "' . $schema . '"');
        DB::statement('SET search_path TO "' . $schema . '", public');

        if (! Schema::hasTable('patients')) {
            self::createPatientsTable();
            self::addPatientForeignKeys($schema);
        }
        if (! Schema::hasTable('prescriptions')) {
            self::createPrescriptionsTable();
            self::addPrescriptionForeignKeys($schema);
        }
        if (! Schema::hasTable('lac_exams')) {
            self::createLacExamsTable();
            self::addLacExamForeignKeys($schema);
        }

        DB::statement('SET search_path TO public');
    }

    public static function provisionPatientsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('CREATE SCHEMA IF NOT EXISTS "' . $schema . '"');
            DB::statement('SET search_path TO "' . $schema . '", public');
            if (! Schema::hasTable('patients')) {
                self::createPatientsTable();
                self::addPatientForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionPrescriptionsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "' . $schema . '", public');
            if (Schema::hasTable('patients') && ! Schema::hasTable('prescriptions')) {
                self::createPrescriptionsTable();
                self::addPrescriptionForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionLacExamsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "' . $schema . '", public');
            if (Schema::hasTable('patients') && ! Schema::hasTable('lac_exams')) {
                self::createLacExamsTable();
                self::addLacExamForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropPatientsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "' . $schema . '", public');
            Schema::dropIfExists('patients');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropPrescriptionsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "' . $schema . '", public');
            Schema::dropIfExists('prescriptions');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropLacExamsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "' . $schema . '", public');
            Schema::dropIfExists('lac_exams');
            DB::statement('SET search_path TO public');
        }
    }

    public static function createPatientsTable(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('pos_id');
            $table->string('title')->nullable();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('last_name2')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('cap', 16)->nullable();
            $table->string('province', 8)->nullable();
            $table->string('country', 2)->default('IT');
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->text('fiscal_code')->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->string('phone')->nullable();
            $table->string('phone2')->nullable();
            $table->string('mobile')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('email_pec')->nullable();
            $table->string('fe_recipient_code', 16)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_cap', 16)->nullable();
            $table->string('billing_province', 8)->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->uuid('family_head_id')->nullable()->index();
            $table->string('language', 8)->default('it');
            $table->string('profession')->nullable();
            $table->string('visual_problem')->nullable();
            $table->string('hobby')->nullable();
            $table->string('referral_source')->nullable();
            $table->string('referral_note')->nullable();
            $table->uuid('referred_by_patient_id')->nullable()->index();
            $table->boolean('card_member')->default(false);
            $table->boolean('uses_contact_lenses')->default(false);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->boolean('gdpr_marketing_consent')->default(false);
            $table->boolean('gdpr_profiling_consent')->default(false);
            $table->string('gdpr_model_printed')->nullable();
            $table->boolean('communication_sms')->default(false);
            $table->boolean('communication_mail')->default(true);
            $table->boolean('communication_letter')->default(false);
            $table->text('notes')->nullable();
            $table->text('private_notes')->nullable();
            $table->foreignId('inserted_by_user_id')->nullable()->index();
            $table->uuid('inserted_at_pos_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('fiscal_code');
            $table->index('mobile');
        });
    }

    public static function createPrescriptionsTable(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('pos_id');
            $table->foreignId('optician_user_id')->nullable()->index();
            $table->date('visit_date');
            $table->boolean('is_international')->default(true);

            foreach (['od', 'os'] as $eye) {
                foreach (['far', 'medium', 'near'] as $dist) {
                    $table->decimal("{$eye}_sphere_{$dist}", 5, 2)->nullable();
                    $table->decimal("{$eye}_cylinder_{$dist}", 5, 2)->nullable();
                    $table->integer("{$eye}_axis_{$dist}")->nullable();
                    $table->decimal("{$eye}_prism_{$dist}", 4, 2)->nullable();
                    $table->string("{$eye}_base_{$dist}")->nullable();
                    $table->decimal("{$eye}_addition_{$dist}", 4, 2)->nullable();
                    $table->decimal("{$eye}_prism_h_{$dist}", 4, 2)->nullable();
                    $table->string("{$eye}_base_h_{$dist}")->nullable();
                    $table->decimal("{$eye}_prism_v_{$dist}", 4, 2)->nullable();
                    $table->string("{$eye}_base_v_{$dist}")->nullable();
                }
            }

            $table->string('visus_od_natural')->nullable();
            $table->string('visus_od_corrected')->nullable();
            $table->string('visus_os_natural')->nullable();
            $table->string('visus_os_corrected')->nullable();
            $table->string('visus_bino_natural')->nullable();
            $table->string('visus_bino_corrected')->nullable();

            $table->string('phoria_far_natural')->nullable();
            $table->string('phoria_far_corrected')->nullable();
            $table->string('phoria_near_natural')->nullable();
            $table->string('phoria_near_corrected')->nullable();

            $table->string('dominant_eye_far')->nullable();
            $table->string('dominant_eye_near')->nullable();

            $table->decimal('ipd_total', 4, 1)->nullable();
            $table->decimal('ipd_right', 4, 1)->nullable();
            $table->decimal('ipd_left', 4, 1)->nullable();

            $table->boolean('glasses_in_use')->default(false);

            $table->string('prescribed_by')->nullable();
            $table->date('prescribed_at')->nullable();
            $table->string('checked_by')->nullable();

            $table->date('next_recall_at')->nullable();
            $table->string('next_recall_reason')->nullable();
            $table->date('next_recall2_at')->nullable();
            $table->string('next_recall2_reason')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    public static function createLacExamsTable(): void
    {
        Schema::create('lac_exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('pos_id');
            $table->foreignId('optician_user_id')->nullable()->index();
            $table->date('exam_date');

            foreach (['od', 'os'] as $eye) {
                $table->decimal("{$eye}_r1", 5, 2)->nullable();
                $table->decimal("{$eye}_r2", 5, 2)->nullable();
                $table->decimal("{$eye}_r1_mm", 5, 2)->nullable();
                $table->decimal("{$eye}_r2_mm", 5, 2)->nullable();
                $table->decimal("{$eye}_media", 5, 2)->nullable();
                $table->integer("{$eye}_ax_r2")->nullable();
                $table->decimal("{$eye}_pupil_diameter", 4, 1)->nullable();
                $table->decimal("{$eye}_corneal_diameter", 4, 1)->nullable();
                $table->decimal("{$eye}_palpebral_aperture", 4, 1)->nullable();
                $table->string("{$eye}_but_test")->nullable();
                $table->string("{$eye}_schirmer_test")->nullable();
                $table->string("{$eye}_visual_problem")->nullable();
                $table->text("{$eye}_notes")->nullable();
            }

            $table->jsonb('tabs_completed')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    protected static function addPatientForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_organization_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_family_head_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_family_head_id_foreign FOREIGN KEY (family_head_id) REFERENCES \"{$schema}\".patients(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_referred_by_patient_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_referred_by_patient_id_foreign FOREIGN KEY (referred_by_patient_id) REFERENCES \"{$schema}\".patients(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_inserted_by_user_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_inserted_by_user_id_foreign FOREIGN KEY (inserted_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".patients DROP CONSTRAINT IF EXISTS patients_inserted_at_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".patients ADD CONSTRAINT patients_inserted_at_pos_id_foreign FOREIGN KEY (inserted_at_pos_id) REFERENCES public.points_of_sale(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addPrescriptionForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".prescriptions DROP CONSTRAINT IF EXISTS prescriptions_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".prescriptions ADD CONSTRAINT prescriptions_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".prescriptions DROP CONSTRAINT IF EXISTS prescriptions_optician_user_id_foreign",
            "ALTER TABLE \"{$schema}\".prescriptions ADD CONSTRAINT prescriptions_optician_user_id_foreign FOREIGN KEY (optician_user_id) REFERENCES public.users(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addLacExamForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".lac_exams DROP CONSTRAINT IF EXISTS lac_exams_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".lac_exams ADD CONSTRAINT lac_exams_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".lac_exams DROP CONSTRAINT IF EXISTS lac_exams_optician_user_id_foreign",
            "ALTER TABLE \"{$schema}\".lac_exams ADD CONSTRAINT lac_exams_optician_user_id_foreign FOREIGN KEY (optician_user_id) REFERENCES public.users(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }
}
