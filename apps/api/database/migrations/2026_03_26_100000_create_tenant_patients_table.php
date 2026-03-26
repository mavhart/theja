<?php

use App\Support\TenantClinicalSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        TenantClinicalSchema::provisionPatientsForAllOrganizations();
    }

    public function down(): void
    {
        TenantClinicalSchema::dropPatientsForAllOrganizations();
    }
};
