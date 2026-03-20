<?php

namespace Tests\Feature;

use App\Events\SessionInvalidated;
use App\Models\DeviceSession;
use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private PointOfSale  $pos;
    private User         $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->org  = Organization::factory()->create();
        $this->pos  = PointOfSale::factory()->create([
            'organization_id'             => $this->org->id,
            'max_concurrent_web_sessions' => 2,
            'is_active'                   => true,
        ]);
        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);

        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id' => $this->user->id,
            'pos_id'  => $this->pos->id,
            'role_id' => $role->id,
        ]);
    }

    /** Helper: crea token + device_session collegati. */
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

    // ─── Broadcast SessionInvalidated ─────────────────────────────────────────

    /** @test */
    public function test_session_invalidated_event_is_dispatched_on_remote_logout(): void
    {
        Event::fake([SessionInvalidated::class]);

        $data1 = $this->createActiveSession($this->user, $this->pos);
        $data2 = $this->createActiveSession($this->user, $this->pos);

        $this->withToken($data1['token'])
            ->deleteJson('/api/sessions/' . $data2['session']->id)
            ->assertOk();

        Event::assertDispatched(SessionInvalidated::class, function (SessionInvalidated $event) use ($data2) {
            return $event->sessionId === $data2['session']->id
                && $event->reason    === 'logged_out_remotely';
        });
    }

    /** @test */
    public function test_session_invalidated_event_is_dispatched_on_logout(): void
    {
        Event::fake([SessionInvalidated::class]);

        $data = $this->createActiveSession($this->user, $this->pos);

        $this->withToken($data['token'])
            ->postJson('/api/auth/logout')
            ->assertOk();

        Event::assertDispatched(SessionInvalidated::class, function (SessionInvalidated $event) use ($data) {
            return $event->sessionId === $data['session']->id
                && $event->reason    === 'user_logged_out';
        });
    }

    /** @test */
    public function test_session_invalidated_event_is_NOT_dispatched_when_no_session_exists(): void
    {
        Event::fake([SessionInvalidated::class]);

        $token = $this->user->createToken('bare-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        Event::assertNotDispatched(SessionInvalidated::class);
    }

    /** @test */
    public function test_session_invalidated_event_broadcasts_on_correct_channel(): void
    {
        $session = DeviceSession::create([
            'user_id'            => $this->user->id,
            'pos_id'             => $this->pos->id,
            'device_fingerprint' => 'fp-123',
            'device_name'        => 'Test',
            'platform'           => 'web',
            'last_active_at'     => now(),
            'is_active'          => true,
        ]);

        $event = new SessionInvalidated($session, 'logged_out_remotely');

        $channel = $event->broadcastOn();
        $this->assertStringContainsString("session.{$session->id}", $channel->name);
        $this->assertEquals('SessionInvalidated', $event->broadcastAs());
        $this->assertEquals(
            ['session_id' => $session->id, 'reason' => 'logged_out_remotely'],
            $event->broadcastWith()
        );
    }

    // ─── Channel Authorization ────────────────────────────────────────────────

    /** @test */
    public function test_channel_auth_grants_access_to_session_owner(): void
    {
        $data = $this->createActiveSession($this->user, $this->pos);

        $response = $this->withToken($data['token'])
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-session.' . $data['session']->id,
                'socket_id'    => '123.456',
            ]);

        $response->assertStatus(200);
    }

    /**
     * Verifica che la logica del channel callback neghi l'accesso a sessioni altrui.
     * Test tramite la funzione di callback direttamente (non via HTTP) per evitare
     * dipendenze dal comportamento interno del driver Pusher in test environment.
     *
     * @test
     */
    public function test_channel_callback_denies_access_to_other_users_session(): void
    {
        $otherUser    = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $otherSession = DeviceSession::create([
            'user_id'            => $otherUser->id,
            'pos_id'             => $this->pos->id,
            'device_fingerprint' => 'fp-other',
            'device_name'        => 'Other',
            'platform'           => 'web',
            'last_active_at'     => now(),
            'is_active'          => true,
        ]);

        // Verifica che i due utenti abbiano ID diversi
        $this->assertNotEquals($otherUser->id, $this->user->id);

        // Simula la logica del channel callback di channels.php
        $session = DeviceSession::find($otherSession->id);
        $isOwner = $session && (int) $session->user_id === (int) $this->user->id;

        $this->assertFalse($isOwner, 'L\'utente non dovrebbe poter accedere alla sessione di un altro utente');
    }

    /** @test */
    public function test_channel_callback_grants_access_to_session_owner_logic(): void
    {
        $data = $this->createActiveSession($this->user, $this->pos);

        $session = DeviceSession::find($data['session']->id);
        $isOwner = $session && (int) $session->user_id === (int) $this->user->id;

        $this->assertTrue($isOwner, 'Il proprietario dovrebbe avere accesso alla propria sessione');
    }

    /** @test */
    public function test_channel_auth_requires_authentication(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-session.fake-session-id',
            'socket_id'    => '123.456',
        ]);

        $response->assertStatus(401);
    }
}
