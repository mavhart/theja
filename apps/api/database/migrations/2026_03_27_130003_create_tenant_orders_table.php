<?php

use App\Support\TenantClinicalSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantClinicalSchema::provisionOrdersForAllOrganizations();
    }

    public function down(): void
    {
        TenantClinicalSchema::dropOrdersForAllOrganizations();
    }
};