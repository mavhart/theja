<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use App\Support\TenantClinicalSchema;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private PointOfSale $pos1;

    private PointOfSale $pos2;

    private User $user;

    protected function tearDown(): void
    {
        DB::statement('SET search_path TO public');
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->org = Organization::factory()->create();

        $this->pos1 = PointOfSale::factory()->create([
            'organization_id'             => $this->org->id,
            'name'                        => 'POS Uno',
            'max_concurrent_web_sessions' => 5,
            'is_active'                   => true,
        ]);

        $this->pos2 = PointOfSale::factory()->create([
            'organization_id'             => $this->org->id,
            'name'                        => 'POS Due',
            'max_concurrent_web_sessions' => 5,
            'is_active'                   => true,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'email'           => 'patient-test@theja.test',
            'password'        => Hash::make('password'),
            'is_active'       => true,
        ]);

        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id'                 => $this->user->id,
            'pos_id'                  => $this->pos1->id,
            'role_id'                 => $role->id,
            'can_see_purchase_prices' => false,
        ]);
        UserPosRole::create([
            'user_id'                 => $this->user->id,
            'pos_id'                  => $this->pos2->id,
            'role_id'                 => $role->id,
            'can_see_purchase_prices' => false,
        ]);
        // Schema tenant + tabelle cliniche: OrganizationObserver su Organization::factory()->create()
    }

    private function authHeaders(): array
    {
        $login = $this->postJson('/api/auth/login', [
            'email'    => 'patient-test@theja.test',
            'password' => 'password',
        ]);

        $token = $login->json('token');

        if ($login->json('requires_pos_selection')) {
            $this->postJson('/api/auth/select-pos', ['pos_id' => $this->pos1->id], [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ])->assertOk();
        }

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_create_patient_with_full_payload(): void
    {
        $headers = $this->authHeaders();

        $payload = [
            'pos_id'                   => $this->pos1->id,
            'title'                    => 'Sig.',
            'last_name'                => 'Rossi',
            'first_name'               => 'Mario',
            'last_name2'               => 'Verdi',
            'gender'                   => 'M',
            'address'                  => 'Via Test 1',
            'city'                     => 'Milano',
            'cap'                      => '20100',
            'province'                 => 'MI',
            'country'                  => 'IT',
            'date_of_birth'            => '1980-05-15',
            'place_of_birth'           => 'Roma',
            'fiscal_code'              => 'RSSMRA80E15H501Z',
            'mobile'                   => '3201234567',
            'email'                    => 'mario@example.it',
            'gdpr_marketing_consent'   => true,
            'communication_mail'       => true,
        ];

        $response = $this->postJson('/api/patients', $payload, $headers);

        $response->assertCreated()
            ->assertJsonPath('data.last_name', 'Rossi')
            ->assertJsonPath('data.fiscal_code', 'RSSMRA80E15H501Z');

        $id = $response->json('data.id');
        $this->assertNotNull($id);
    }

    public function test_search_patient_by_name_mobile_and_fiscal_code(): void
    {
        $headers = $this->authHeaders();

        $this->postJson('/api/patients', [
            'pos_id'      => $this->pos1->id,
            'last_name'   => 'Bianchi',
            'first_name'  => 'Anna',
            'mobile'      => '3339988776',
            'fiscal_code' => 'BNCHNA90A41F205X',
        ], $headers)->assertCreated();

        $r1 = $this->getJson('/api/patients?q=Bianchi', $headers);
        $r1->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r1->json('data')));

        $r2 = $this->getJson('/api/patients?q=3339988776', $headers);
        $r2->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r2->json('data')));

        $r3 = $this->getJson('/api/patients?q=BNCHNA90A41F205X', $headers);
        $r3->assertOk();
        $this->assertGreaterThanOrEqual(1, count($r3->json('data')));
    }

    public function test_create_prescription_for_patient(): void
    {
        $headers = $this->authHeaders();

        $p = $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'Verdi',
            'first_name' => 'Luigi',
        ], $headers);

        $patientId = $p->json('data.id');

        $res = $this->postJson('/api/prescriptions', [
            'patient_id'       => $patientId,
            'pos_id'           => $this->pos1->id,
            'visit_date'       => '2026-03-01',
            'is_international' => true,
            'od_sphere_far'    => -2.50,
            'os_sphere_far'    => -2.25,
            'notes'            => 'Test visita',
        ], $headers);

        $res->assertCreated();
        $this->assertEquals(-2.5, (float) $res->json('data.od_sphere_far'));

        $schema = TenantClinicalSchema::schemaNameForOrganizationId($this->org->id);
        $cnt = DB::selectOne(
            "SELECT COUNT(*)::int AS c FROM \"{$schema}\".prescriptions WHERE patient_id = ?",
            [$patientId]
        );
        $this->assertSame(1, $cnt->c);
    }

    public function test_patient_visible_from_all_pos_same_org(): void
    {
        $headers = $this->authHeaders();

        $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'MultiPOS',
            'first_name' => 'Test',
        ], $headers)->assertCreated();

        $list = $this->getJson('/api/patients?q=MultiPOS', $headers);
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));

        // Cambia POS attivo: stesso paziente deve restare visibile (stesso schema tenant)
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $this->postJson('/api/auth/select-pos', ['pos_id' => $this->pos2->id], [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->assertOk();

        $headersPos2 = [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
        $list2 = $this->getJson('/api/patients?q=MultiPOS', $headersPos2);
        $list2->assertOk();
        $this->assertCount(1, $list2->json('data'));
    }

    public function test_fiscal_code_not_stored_plain_in_database(): void
    {
        $headers = $this->authHeaders();

        $this->postJson('/api/patients', [
            'pos_id'      => $this->pos1->id,
            'last_name'   => 'Crypto',
            'first_name'  => 'Test',
            'fiscal_code' => 'CRYPTE00A00A000A',
        ], $headers)->assertCreated();

        $schema = TenantClinicalSchema::schemaNameForOrganizationId($this->org->id);
        $row = DB::selectOne("SELECT fiscal_code FROM \"{$schema}\".patients WHERE last_name = 'Crypto' LIMIT 1");

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('CRYPTE00A00A000A', (string) $row->fiscal_code);
    }
}
