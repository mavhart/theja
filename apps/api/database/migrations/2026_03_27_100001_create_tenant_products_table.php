<?php

use App\Support\TenantClinicalSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantClinicalSchema::provisionProductsForAllOrganizations();
    }

    public function down(): void
    {
        TenantClinicalSchema::dropProductsForAllOrganizations();
    }
};
