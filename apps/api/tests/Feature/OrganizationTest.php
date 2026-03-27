<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    /** Creazione organization base con tutti i campi richiesti */
    public function test_can_create_organization(): void
    {
        $org = Organization::create([
            'name'          => 'Ottica Test Srl',
            'vat_number'    => 'IT12345678901',
            'billing_email' => 'test@otticatest.it',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name'       => 'Ottica Test Srl',
            'vat_number' => 'IT12345678901',
            'is_active'  => true,
        ]);

        $this->assertNotNull($org->id);
        $this->assertTrue(is_string($org->id));  // UUID è una stringa
    }

    /** UUID è generato automaticamente e non è vuoto */
    public function test_organization_has_uuid_primary_key(): void
    {
        $org = Organization::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $org->id
        );
    }

    /** Creazione POS associato a una Organization */
    public function test_can_create_point_of_sale_for_organization(): void
    {
        $org = Organization::factory()->create();

        $pos = PointOfSale::create([
            'organization_id'             => $org->id,
            'name'                        => 'POS Test — Centro',
            'city'                        => 'Milano',
            'max_concurrent_web_sessions' => 1,
        ]);

        $this->assertDatabaseHas('points_of_sale', [
            'organization_id' => $org->id,
            'name'            => 'POS Test — Centro',
            'is_active'       => true,
        ]);

        $this->assertEquals($org->id, $pos->organization->id);
    }

    /** Una Organization può avere più POS */
    public function test_organization_can_have_multiple_points_of_sale(): void
    {
        $org = Organization::factory()->create();

        PointOfSale::factory()->count(3)->create(['organization_id' => $org->id]);

        $this->assertCount(3, $org->pointsOfSale);
    }

    /** I feature flag POS partono tutti a false di default */
    public function test_points_of_sale_feature_flags_default_to_false(): void
    {
        $org = Organization::factory()->create();
        $pos = PointOfSale::factory()->create(['organization_id' => $org->id]);

        $this->assertFalse($pos->virtual_cash_register_enabled);
        $this->assertFalse($pos->cash_register_hardware_configured);
        $this->assertFalse($pos->ai_analysis_enabled);
        $this->assertEquals(1, $pos->max_concurrent_web_sessions);
        $this->assertEquals(0, $pos->max_mobile_devices);
    }

    /** Un utente appartiene a una Organization */
    public function test_user_belongs_to_organization(): void
    {
        $org  = Organization::factory()->create();
        $user = User::factory()->forOrganization($org)->create();

        $this->assertEquals($org->id, $user->organization_id);
        $this->assertEquals($org->id, $user->organization->id);
    }

    /** getTenantSchemaName restituisce il nome schema corretto (senza trattini) */
    public function test_organization_returns_correct_tenant_schema_name(): void
    {
        $org = Organization::factory()->create();

        $schema = $org->getTenantSchemaName();

        $this->assertStringStartsWith('tenant_', $schema);
        $this->assertStringNotContainsString('-', $schema);
        $this->assertEquals(
            'tenant_' . str_replace('-', '', $org->id),
            $schema
        );
    }
}
