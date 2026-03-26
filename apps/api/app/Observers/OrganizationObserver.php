<?php

namespace App\Observers;

use App\Models\Organization;
use App\Support\TenantClinicalSchema;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        TenantClinicalSchema::provisionAllClinicalForOrganization($organization->id);
    }
}
