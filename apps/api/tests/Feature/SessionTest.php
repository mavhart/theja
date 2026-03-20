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

class SessionTest extends TestCase
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
            'max_concurrent_web_sessions' => 1,
            'ai_analysis_enabled'         => false,
            'is_active'                   => true,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'password'        => Hash::make('password'),
            'is_active'       => true,
        ]);

        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id' => $this->user->id,
            'pos_id'  => $this->pos->id,
            'role_id' => $role->id,
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /** Crea un token Sanctum e la relativa device_session. */
    private function createActiveSession(User $user, PointOfSale $pos): array
    {
        $token       = $user->createToken('device')->plainTextToken;
        $tokenRecord = $user->tokens()->latest()->first();

        $session = DeviceSession::create([
            'user_id'            => $user->id,
            'pos_id'             => $pos->id,
            'sanctum_token_id'   => $tokenRecord->id,
            'device_fingerprint' => 'fp-' . uniqid(),
            'device_name'        => 'Test Browser',
            'platform'           => 'web',
            'ip_address'         => '127.0.0.1',
            'last_active_at'     => now(),
            'is_active'          => true,
        ]);

        return ['token' => $token, 'session' => $session];
    }

    // ─── EnforceSessionLimit ─────────────────────────────────────────────────

    /** @test */
    public function test_enforce_session_limit_blocks_when_limit_exceeded(): void
    {
        // POS ha max=1 ma creiamo 2 sessioni attive → limite superato
        $data1 = $this->createActiveSession($this->user, $this->pos);
        $this->createActiveSession($this->user, $this->pos);

        // La route /api/tenant/schema è tenant-aware e usa EnforceSessionLimit
        $response = $this->withToken($data1['token'])
            ->getJson('/api/tenant/schema');

        $response->assertStatus(423)
            ->assertJsonFragment(['error' => 'session_limit_reached'])
            ->assertJsonStructure(['error', 'active_sessions']);

        $this->assertCount(2, $response->json('active_sessions'));
    }

    /** @test */
    public function test_session_within_limit_passes_successfully(): void
    {
        // POS ha max=1 e c'è solo 1 sessione attiva → passa
        $data = $this->createActiveSession($this->user, $this->pos);

        $response = $this->withToken($data['token'])
            ->getJson('/api/tenant/schema');

        $response->assertOk();
    }

    // ─── GET /api/sessions ───────────────────────────────────────────────────

    /** @test */
    public function test_list_sessions_returns_active_sessions(): void
    {
        $data = $this->createActiveSession($this->user, $this->pos);

        $response = $this->withToken($data['token'])
            ->getJson('/api/sessions');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'device_name', 'platform', 'last_active_at']]]);

        $this->assertCount(1, $response->json('data'));
    }

    // ─── DELETE /api/sessions/{id} ────────────────────────────────────────────

    /** @test */
    public function test_delete_session_invalidates_remote_session(): void
    {
        $data1 = $this->createActiveSession($this->user, $this->pos);
        $data2 = $this->createActiveSession($this->user, $this->pos);

        // L'utente su device1 invalida la sessione di device2 (logout remoto)
        $response = $this->withToken($data1['token'])
            ->deleteJson('/api/sessions/' . $data2['session']->id);

        $response->assertOk()
            ->assertJson(['message' => 'Sessione invalidata.']);

        $this->assertFalse(DeviceSession::find($data2['session']->id)->is_active);
    }

    /** @test */
    public function test_delete_session_of_another_user_returns_404(): void
    {
        $otherUser = User::factory()->create([
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);

        // Crea sessione per un utente diverso usando token diretto
        $otherToken       = $otherUser->createToken('other')->plainTextToken;
        $otherTokenRecord = $otherUser->tokens()->latest()->first();

        $otherSession = DeviceSession::create([
            'user_id'            => $otherUser->id,
            'pos_id'             => $this->pos->id,
            'sanctum_token_id'   => $otherTokenRecord->id,
            'device_fingerprint' => 'fp-other',
            'device_name'        => 'Other Device',
            'platform'           => 'web',
            'last_active_at'     => now(),
            'is_active'          => true,
        ]);

        // Il nostro utente non può invalidare la sessione dell'altro
        $myData = $this->createActiveSession($this->user, $this->pos);

        $this->withToken($myData['token'])
            ->deleteJson('/api/sessions/' . $otherSession->id)
            ->assertStatus(404);
    }

    // ─── CheckFeatureActive ───────────────────────────────────────────────────

    /** @test */
    public function test_check_feature_active_blocks_with_403_when_feature_disabled(): void
    {
        // Il POS ha ai_analysis_enabled=false
        $data = $this->createActiveSession($this->user, $this->pos);

        $response = $this->withToken($data['token'])
            ->getJson('/api/ai/analyze');

        $response->assertStatus(403)
            ->assertJson(['error' => 'feature_not_active']);
    }

    /** @test */
    public function test_check_feature_active_allows_when_feature_enabled(): void
    {
        $this->pos->update(['ai_analysis_enabled' => true]);
        $data = $this->createActiveSession($this->user, $this->pos);

        $response = $this->withToken($data['token'])
            ->getJson('/api/ai/analyze');

        $response->assertOk();
    }
}
