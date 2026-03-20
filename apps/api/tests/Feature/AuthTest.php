<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private PointOfSale  $pos;
    private User         $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->org = Organization::factory()->create();

        $this->pos = PointOfSale::factory()->create([
            'organization_id'             => $this->org->id,
            'max_concurrent_web_sessions' => 2,
            'is_active'                   => true,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'email'           => 'test@theja.test',
            'password'        => Hash::make('password'),
            'is_active'       => true,
        ]);

        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id'                 => $this->user->id,
            'pos_id'                  => $this->pos->id,
            'role_id'                 => $role->id,
            'can_see_purchase_prices' => false,
        ]);
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    /** @test */
    public function test_login_with_valid_credentials_returns_token_and_pos_list(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user'         => ['id', 'name', 'email', 'organization_id', 'is_active'],
                'points_of_sale',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertCount(1, $response->json('points_of_sale'));
    }

    /** @test */
    public function test_login_with_single_pos_auto_selects_it(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson(['requires_pos_selection' => false])
            ->assertJsonStructure(['active_pos', 'permissions', 'session_id']);
    }

    /** @test */
    public function test_login_with_multiple_pos_requires_selection(): void
    {
        // Aggiunge un secondo POS accessibile allo stesso utente
        $pos2 = PointOfSale::factory()->create([
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);
        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id' => $this->user->id,
            'pos_id'  => $pos2->id,
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson(['requires_pos_selection' => true])
            ->assertJsonCount(2, 'points_of_sale');
    }

    /** @test */
    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Credenziali non valide.']);
    }

    /** @test */
    public function test_login_with_inactive_user_returns_401(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Account disabilitato.']);
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_logout_revokes_token(): void
    {
        $tokenRecord = $this->user->createToken('test');
        $token       = $tokenRecord->plainTextToken;
        $tokenId     = $tokenRecord->accessToken->id;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logout effettuato.']);

        // Il token deve essere eliminato dal database
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        // Reset guard cache e verifica che il token non autentica più
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /** @test */
    public function test_logout_invalidates_device_session(): void
    {
        // Login (auto-seleziona il POS)
        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);
        $token     = $loginResponse->json('token');
        $sessionId = $loginResponse->json('session_id');

        $this->withToken($token)->postJson('/api/auth/logout');

        $session = DeviceSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertFalse($session->is_active);
    }

    // ─── Me ───────────────────────────────────────────────────────────────────

    /** @test */
    public function test_me_returns_user_and_active_pos(): void
    {
        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'test@theja.test',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user'        => ['id', 'name', 'email'],
                'active_pos',
                'permissions',
                'session_id',
            ]);
    }
}
