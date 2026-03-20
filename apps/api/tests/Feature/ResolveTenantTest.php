<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResolveTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Ripristina il search_path a public dopo ogni test
        // per evitare interferenze tra i test
        DB::statement('SET search_path TO public');
        parent::tearDown();
    }

    /** Senza token → 401 */
    public function test_request_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/tenant/schema');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** Token invalido (stringa casuale) → 401 */
    public function test_request_with_invalid_token_returns_401(): void
    {
        $response = $this->withToken('not-a-valid-token')->getJson('/api/tenant/schema');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** Utente senza organization → 401 */
    public function test_request_from_user_without_organization_returns_401(): void
    {
        $user  = User::factory()->create(['organization_id' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/tenant/schema');

        $response->assertStatus(401)
            ->assertJson(['message' => 'User has no organization associated.']);
    }

    /** Token valido con utente e organization → 200 e search_path switchato */
    public function test_resolve_tenant_switches_postgresql_search_path(): void
    {
        $org   = Organization::factory()->create();
        $user  = User::factory()->forOrganization($org)->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $expectedSchema = 'tenant_' . str_replace('-', '', $org->id);

        $response = $this->withToken($token)->getJson('/api/tenant/schema');

        $response->assertOk();

        $searchPath = $response->json('search_path');
        $this->assertStringContainsString($expectedSchema, $searchPath);
    }

    /** Due utenti di org diverse vedono search_path differenti */
    public function test_different_organizations_get_different_search_paths(): void
    {
        $org1  = Organization::factory()->create();
        $org2  = Organization::factory()->create();
        $user1 = User::factory()->forOrganization($org1)->create();
        $user2 = User::factory()->forOrganization($org2)->create();

        $token1 = $user1->createToken('device-1')->plainTextToken;
        $token2 = $user2->createToken('device-2')->plainTextToken;

        $schema1 = 'tenant_' . str_replace('-', '', $org1->id);
        $schema2 = 'tenant_' . str_replace('-', '', $org2->id);

        $res1 = $this->withToken($token1)->getJson('/api/tenant/schema');
        $res2 = $this->withToken($token2)->getJson('/api/tenant/schema');

        $res1->assertOk();
        $res2->assertOk();

        $this->assertStringContainsString($schema1, $res1->json('search_path'));
        $this->assertStringContainsString($schema2, $res2->json('search_path'));
        $this->assertNotEquals($res1->json('search_path'), $res2->json('search_path'));
    }

    /** Health-check pubblico è accessibile senza token */
    public function test_public_health_endpoint_does_not_require_token(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}
