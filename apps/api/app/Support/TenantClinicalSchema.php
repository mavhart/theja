<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle tenant negli schema PostgreSQL (tenant_{orgIdNoHyphens}).
 */
class TenantClinicalSchema
{
    public static function schemaNameForOrganizationId(string $organizationId): string
    {
        return 'tenant_'.str_replace('-', '', $organizationId);
    }

    public static function provisionAllClinicalForOrganization(string $organizationId): void
    {
        $schema = self::schemaNameForOrganizationId($organizationId);
        DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$schema.'"');
        DB::statement('SET search_path TO "'.$schema.'", public');

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
        if (! Schema::hasTable('suppliers')) {
            self::createSuppliersTable();
            self::addSupplierForeignKeys($schema);
        }
        if (! Schema::hasTable('products')) {
            self::createProductsTable();
            self::addProductForeignKeys($schema);
        }
        if (! Schema::hasTable('inventory_items')) {
            self::createInventoryItemsTable();
            self::addInventoryItemForeignKeys($schema);
        }
        if (! Schema::hasTable('stock_movements')) {
            self::createStockMovementsTable();
            self::addStockMovementForeignKeys($schema);
        }
        if (! Schema::hasTable('stock_transfer_requests')) {
            self::createStockTransferRequestsTable();
            self::addStockTransferRequestForeignKeys($schema);
        }
        if (! Schema::hasTable('lac_supply_schedules')) {
            self::createLacSupplySchedulesTable();
            self::addLacSupplyScheduleForeignKeys($schema);
        }
        if (! Schema::hasTable('sales')) {
            self::createSalesTable();
            self::addSalesForeignKeys($schema);
        }
        if (! Schema::hasTable('sale_items')) {
            self::createSaleItemsTable();
            self::addSaleItemsForeignKeys($schema);
        }
        if (! Schema::hasTable('payments')) {
            self::createPaymentsTable();
            self::addPaymentsForeignKeys($schema);
        }
        if (! Schema::hasTable('orders')) {
            self::createOrdersTable();
            self::addOrdersForeignKeys($schema);
        }
        if (! Schema::hasTable('after_sale_events')) {
            self::createAfterSaleEventsTable();
            self::addAfterSaleEventsForeignKeys($schema);
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

    public static function provisionSuppliersForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('suppliers')) {
                self::createSuppliersTable();
                self::addSupplierForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionProductsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('products')) {
                self::createProductsTable();
                self::addProductForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionInventoryItemsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('inventory_items')) {
                self::createInventoryItemsTable();
                self::addInventoryItemForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionStockMovementsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('stock_movements')) {
                self::createStockMovementsTable();
                self::addStockMovementForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionStockTransferRequestsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('stock_transfer_requests')) {
                self::createStockTransferRequestsTable();
                self::addStockTransferRequestForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionLacSupplySchedulesForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('lac_supply_schedules')) {
                self::createLacSupplySchedulesTable();
                self::addLacSupplyScheduleForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionSalesForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('sales')) {
                self::createSalesTable();
                self::addSalesForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionSaleItemsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('sale_items')) {
                self::createSaleItemsTable();
                self::addSaleItemsForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionPaymentsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('payments')) {
                self::createPaymentsTable();
                self::addPaymentsForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionOrdersForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('orders')) {
                self::createOrdersTable();
                self::addOrdersForeignKeys($schema);
            }
            DB::statement('SET search_path TO public');
        }
    }

    public static function provisionAfterSaleEventsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            if (! Schema::hasTable('after_sale_events')) {
                self::createAfterSaleEventsTable();
                self::addAfterSaleEventsForeignKeys($schema);
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

    public static function dropSuppliersForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('suppliers');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropProductsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('products');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropInventoryItemsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('inventory_items');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropStockMovementsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('stock_movements');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropStockTransferRequestsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('stock_transfer_requests');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropLacSupplySchedulesForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('lac_supply_schedules');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropSalesForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('sales');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropSaleItemsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('sale_items');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropPaymentsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('payments');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropOrdersForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('orders');
            DB::statement('SET search_path TO public');
        }
    }

    public static function dropAfterSaleEventsForAllOrganizations(): void
    {
        foreach (DB::table('organizations')->pluck('id') as $id) {
            $schema = self::schemaNameForOrganizationId((string) $id);
            DB::statement('SET search_path TO "'.$schema.'", public');
            Schema::dropIfExists('after_sale_events');
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

    public static function createSuppliersTable(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('type', 16);
            $table->string('company_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('cap', 16)->nullable();
            $table->string('province', 8)->nullable();
            $table->string('country', 2)->default('IT');
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('toll_free')->nullable();
            $table->string('store_code')->nullable();
            $table->string('fiscal_code', 32)->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->string('pec')->nullable();
            $table->string('fe_recipient_code', 16)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('abi', 16)->nullable();
            $table->string('cab', 16)->nullable();
            $table->string('bic_swift', 16)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('account_number')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('accountant_code')->nullable();
            $table->string('user_id_catalog')->nullable();
            $table->string('password_catalog')->nullable();
            $table->string('user_id_images')->nullable();
            $table->string('password_images')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('categories')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('code');
            $table->index('company_name');
        });
    }

    public static function createProductsTable(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('supplier_id')->nullable()->index();
            $table->string('category', 32);
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable();
            $table->string('internal_code')->nullable();
            $table->string('personal_code')->nullable();
            $table->string('brand')->nullable();
            $table->string('line')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->string('lens_type')->nullable();
            $table->string('lens_color')->nullable();
            $table->string('user_type', 16)->nullable();
            $table->string('mounting_type')->nullable();
            $table->integer('caliber')->nullable();
            $table->integer('bridge')->nullable();
            $table->integer('temple')->nullable();
            $table->boolean('is_polarized')->default(false);
            $table->boolean('is_ce')->default(false);
            $table->jsonb('attributes')->default('{}');
            $table->text('purchase_price')->nullable();
            $table->decimal('markup_percent', 5, 2)->nullable();
            $table->decimal('net_price', 10, 2)->nullable();
            $table->decimal('list_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('vat_code')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->date('inserted_at')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('customs_code')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('category');
            $table->index('barcode');
            $table->index('sku');
            $table->index('internal_code');
            $table->index('brand');
            $table->index('model');
        });
    }

    public static function createInventoryItemsTable(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_id');
            $table->uuid('product_id');
            $table->integer('quantity')->default(0);
            $table->integer('quantity_arriving')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);
            $table->date('last_purchase_date')->nullable();
            $table->date('last_sale_date')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->unique(['pos_id', 'product_id']);
        });
    }

    public static function createStockMovementsTable(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_id');
            $table->uuid('product_id');
            $table->foreignId('user_id')->nullable()->index();
            $table->string('type', 32);
            $table->integer('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('ddt_number')->nullable();
            $table->date('ddt_date')->nullable();
            $table->uuid('supplier_id')->nullable()->index();
            $table->string('reference')->nullable();
            $table->string('lot')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pos_id', 'product_id']);
            $table->index('type');
        });
    }

    public static function createStockTransferRequestsTable(): void
    {
        Schema::create('stock_transfer_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_pos_id');
            $table->uuid('to_pos_id');
            $table->foreignId('requested_by_user_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->string('status', 16)->default('requested');
            $table->text('rejection_reason')->nullable();
            $table->string('ddt_number')->nullable();
            $table->string('ddt_pdf_path')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['from_pos_id', 'to_pos_id']);
            $table->index('status');
        });
    }

    public static function createLacSupplySchedulesTable(): void
    {
        Schema::create('lac_supply_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('pos_id');
            $table->uuid('product_id');
            $table->date('supply_date');
            $table->integer('quantity');
            $table->integer('estimated_duration_days');
            $table->date('estimated_end_date');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('patient_response', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('estimated_end_date');
        });
    }

    public static function createSalesTable(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_id');
            $table->uuid('patient_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->string('status', 16)->default('quote');
            $table->string('type', 32);
            $table->date('sale_date');
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->uuid('prescription_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'sale_date']);
            $table->index('type');
        });
    }

    public static function createSaleItemsTable(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('product_id')->nullable()->index();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->text('purchase_price')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->string('vat_code')->nullable();
            $table->string('sts_code')->nullable();
            $table->string('lot')->nullable();
            $table->string('item_type', 32)->default('altro');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public static function createPaymentsTable(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->decimal('amount', 10, 2);
            $table->string('method', 16);
            $table->date('payment_date');
            $table->boolean('is_scheduled')->default(false);
            $table->date('scheduled_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public static function createOrdersTable(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_id');
            $table->uuid('sale_id')->nullable()->index();
            $table->uuid('patient_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->uuid('lab_supplier_id')->nullable()->index();
            $table->string('status', 16)->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->string('job_code')->nullable();
            $table->string('frame_barcode')->nullable();
            $table->string('frame_description')->nullable();
            $table->uuid('lens_right_product_id')->nullable()->index();
            $table->uuid('lens_left_product_id')->nullable()->index();
            $table->string('lens_right_description')->nullable();
            $table->string('lens_left_description')->nullable();
            $table->uuid('prescription_id')->nullable()->index();
            $table->string('mounting_type')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['status', 'order_date']);
        });
    }

    public static function createAfterSaleEventsTable(): void
    {
        Schema::create('after_sale_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('sale_item_id')->nullable()->index();
            $table->string('type', 24);
            $table->text('description');
            $table->string('status', 16)->default('aperto');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
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

    protected static function addSupplierForeignKeys(string $schema): void
    {
        DB::statement("ALTER TABLE \"{$schema}\".suppliers DROP CONSTRAINT IF EXISTS suppliers_organization_id_foreign");
        DB::statement("ALTER TABLE \"{$schema}\".suppliers ADD CONSTRAINT suppliers_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE");
    }

    protected static function addProductForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".products DROP CONSTRAINT IF EXISTS products_organization_id_foreign",
            "ALTER TABLE \"{$schema}\".products ADD CONSTRAINT products_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".products DROP CONSTRAINT IF EXISTS products_supplier_id_foreign",
            "ALTER TABLE \"{$schema}\".products ADD CONSTRAINT products_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES \"{$schema}\".suppliers(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addInventoryItemForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".inventory_items DROP CONSTRAINT IF EXISTS inventory_items_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".inventory_items ADD CONSTRAINT inventory_items_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".inventory_items DROP CONSTRAINT IF EXISTS inventory_items_product_id_foreign",
            "ALTER TABLE \"{$schema}\".inventory_items ADD CONSTRAINT inventory_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES \"{$schema}\".products(id) ON DELETE CASCADE",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addStockMovementForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".stock_movements DROP CONSTRAINT IF EXISTS stock_movements_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_movements ADD CONSTRAINT stock_movements_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".stock_movements DROP CONSTRAINT IF EXISTS stock_movements_product_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_movements ADD CONSTRAINT stock_movements_product_id_foreign FOREIGN KEY (product_id) REFERENCES \"{$schema}\".products(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".stock_movements DROP CONSTRAINT IF EXISTS stock_movements_supplier_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_movements ADD CONSTRAINT stock_movements_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES \"{$schema}\".suppliers(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".stock_movements DROP CONSTRAINT IF EXISTS stock_movements_user_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_movements ADD CONSTRAINT stock_movements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addStockTransferRequestForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".stock_transfer_requests DROP CONSTRAINT IF EXISTS stock_transfer_requests_from_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests ADD CONSTRAINT stock_transfer_requests_from_pos_id_foreign FOREIGN KEY (from_pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests DROP CONSTRAINT IF EXISTS stock_transfer_requests_to_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests ADD CONSTRAINT stock_transfer_requests_to_pos_id_foreign FOREIGN KEY (to_pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests DROP CONSTRAINT IF EXISTS stock_transfer_requests_requested_by_user_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests ADD CONSTRAINT stock_transfer_requests_requested_by_user_id_foreign FOREIGN KEY (requested_by_user_id) REFERENCES public.users(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests DROP CONSTRAINT IF EXISTS stock_transfer_requests_product_id_foreign",
            "ALTER TABLE \"{$schema}\".stock_transfer_requests ADD CONSTRAINT stock_transfer_requests_product_id_foreign FOREIGN KEY (product_id) REFERENCES \"{$schema}\".products(id) ON DELETE RESTRICT",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addLacSupplyScheduleForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".lac_supply_schedules DROP CONSTRAINT IF EXISTS lac_supply_schedules_patient_id_foreign",
            "ALTER TABLE \"{$schema}\".lac_supply_schedules ADD CONSTRAINT lac_supply_schedules_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES \"{$schema}\".patients(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".lac_supply_schedules DROP CONSTRAINT IF EXISTS lac_supply_schedules_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".lac_supply_schedules ADD CONSTRAINT lac_supply_schedules_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".lac_supply_schedules DROP CONSTRAINT IF EXISTS lac_supply_schedules_product_id_foreign",
            "ALTER TABLE \"{$schema}\".lac_supply_schedules ADD CONSTRAINT lac_supply_schedules_product_id_foreign FOREIGN KEY (product_id) REFERENCES \"{$schema}\".products(id) ON DELETE RESTRICT",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addSalesForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".sales ADD CONSTRAINT sales_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_patient_id_foreign",
            "ALTER TABLE \"{$schema}\".sales ADD CONSTRAINT sales_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES \"{$schema}\".patients(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_prescription_id_foreign",
            "ALTER TABLE \"{$schema}\".sales ADD CONSTRAINT sales_prescription_id_foreign FOREIGN KEY (prescription_id) REFERENCES \"{$schema}\".prescriptions(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".sales DROP CONSTRAINT IF EXISTS sales_user_id_foreign",
            "ALTER TABLE \"{$schema}\".sales ADD CONSTRAINT sales_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addSaleItemsForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".sale_items DROP CONSTRAINT IF EXISTS sale_items_sale_id_foreign",
            "ALTER TABLE \"{$schema}\".sale_items ADD CONSTRAINT sale_items_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES \"{$schema}\".sales(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".sale_items DROP CONSTRAINT IF EXISTS sale_items_product_id_foreign",
            "ALTER TABLE \"{$schema}\".sale_items ADD CONSTRAINT sale_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES \"{$schema}\".products(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addPaymentsForeignKeys(string $schema): void
    {
        DB::statement("ALTER TABLE \"{$schema}\".payments DROP CONSTRAINT IF EXISTS payments_sale_id_foreign");
        DB::statement("ALTER TABLE \"{$schema}\".payments ADD CONSTRAINT payments_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES \"{$schema}\".sales(id) ON DELETE CASCADE");
    }

    protected static function addOrdersForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_pos_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_pos_id_foreign FOREIGN KEY (pos_id) REFERENCES public.points_of_sale(id) ON DELETE RESTRICT",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_sale_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES \"{$schema}\".sales(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_patient_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES \"{$schema}\".patients(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_lab_supplier_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_lab_supplier_id_foreign FOREIGN KEY (lab_supplier_id) REFERENCES \"{$schema}\".suppliers(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_lens_right_product_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_lens_right_product_id_foreign FOREIGN KEY (lens_right_product_id) REFERENCES \"{$schema}\".products(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_lens_left_product_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_lens_left_product_id_foreign FOREIGN KEY (lens_left_product_id) REFERENCES \"{$schema}\".products(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_prescription_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_prescription_id_foreign FOREIGN KEY (prescription_id) REFERENCES \"{$schema}\".prescriptions(id) ON DELETE SET NULL",
            "ALTER TABLE \"{$schema}\".orders DROP CONSTRAINT IF EXISTS orders_user_id_foreign",
            "ALTER TABLE \"{$schema}\".orders ADD CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }

    protected static function addAfterSaleEventsForeignKeys(string $schema): void
    {
        $sql = [
            "ALTER TABLE \"{$schema}\".after_sale_events DROP CONSTRAINT IF EXISTS after_sale_events_sale_id_foreign",
            "ALTER TABLE \"{$schema}\".after_sale_events ADD CONSTRAINT after_sale_events_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES \"{$schema}\".sales(id) ON DELETE CASCADE",
            "ALTER TABLE \"{$schema}\".after_sale_events DROP CONSTRAINT IF EXISTS after_sale_events_sale_item_id_foreign",
            "ALTER TABLE \"{$schema}\".after_sale_events ADD CONSTRAINT after_sale_events_sale_item_id_foreign FOREIGN KEY (sale_item_id) REFERENCES \"{$schema}\".sale_items(id) ON DELETE SET NULL",
        ];
        foreach ($sql as $s) {
            DB::statement($s);
        }
    }
}
