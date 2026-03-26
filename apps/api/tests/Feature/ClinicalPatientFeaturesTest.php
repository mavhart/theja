<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalPatientFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private PointOfSale $pos1;

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
            'name'                        => 'POS OCR',
            'max_concurrent_web_sessions' => 5,
            'is_active'                   => true,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'email'           => 'clinical-test@theja.test',
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
    }

    private function authHeaders(): array
    {
        $login = $this->postJson('/api/auth/login', [
            'email'    => 'clinical-test@theja.test',
            'password' => 'password',
        ]);

        $token = $login->json('token');

        if ($login->json('requires_pos_selection')) {
            $this->postJson('/api/auth/select-pos', ['pos_id' => $this->pos1->id], [
                'Authorization' => 'Bearer '.$token,
                'Accept'        => 'application/json',
            ])->assertOk();
        }

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ];
    }

    public function test_patient_show_includes_prescription_alert_fields(): void
    {
        $headers = $this->authHeaders();

        $res = $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'Alert',
            'first_name' => 'Test',
        ], $headers)->assertCreated();

        $id = $res->json('data.id');

        $show = $this->getJson("/api/patients/{$id}", $headers);
        $show->assertOk()
            ->assertJsonPath('data.prescription_alert', 'none')
            ->assertJsonPath('data.last_prescription_visit_date', null);
    }

    public function test_ocr_endpoint_returns_fields_with_mocked_openai(): void
    {
        config(['services.openai.key' => 'sk-test-fake']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"od_sphere_far":-2.25,"os_sphere_far":-1.5,"od_cylinder_far":-0.5,"os_cylinder_far":-0.25,"od_axis_far":180,"os_axis_far":175,"od_addition_far":1.0,"os_addition_far":1.0,"confidence":"high"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $headers = $this->authHeaders();

        $p = $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'Ocr',
            'first_name' => 'User',
        ], $headers)->assertCreated();

        $patientId = $p->json('data.id');

        $ocr = $this->postJson("/api/patients/{$patientId}/prescriptions/ocr", [
            'image_base64' => base64_encode('fake-image-bytes'),
        ], $headers);

        $ocr->assertOk()
            ->assertJsonPath('data.od_sphere_far', -2.25)
            ->assertJsonPath('data.confidence', 'high');
    }

    public function test_prescription_pdf_returns_valid_base64(): void
    {
        $headers = $this->authHeaders();

        $p = $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'Pdf',
            'first_name' => 'User',
        ], $headers)->assertCreated();

        $patientId = $p->json('data.id');

        $rx = $this->postJson('/api/prescriptions', [
            'patient_id'       => $patientId,
            'pos_id'           => $this->pos1->id,
            'visit_date'       => '2026-01-10',
            'is_international' => true,
            'od_sphere_far'    => -1,
        ], $headers)->assertCreated();

        $rxId = $rx->json('data.id');

        $pdf = $this->getJson("/api/patients/{$patientId}/prescriptions/{$rxId}/pdf?type=referto", $headers);

        $pdf->assertOk();
        $raw = base64_decode((string) $pdf->json('pdf_base64'), true);
        $this->assertNotFalse($raw);
        $this->assertStringStartsWith('%PDF', $raw);
    }

    public function test_lac_pdf_returns_valid_base64(): void
    {
        $headers = $this->authHeaders();

        $p = $this->postJson('/api/patients', [
            'pos_id'     => $this->pos1->id,
            'last_name'  => 'LacPdf',
            'first_name' => 'User',
        ], $headers)->assertCreated();

        $patientId = $p->json('data.id');

        $exam = $this->postJson('/api/lac-exams', [
            'patient_id' => $patientId,
            'pos_id'     => $this->pos1->id,
            'exam_date'  => '2026-02-01',
            'od_r1'      => 7.5,
        ], $headers)->assertCreated();

        $examId = $exam->json('data.id');

        $pdf = $this->getJson("/api/patients/{$patientId}/lac-exams/{$examId}/pdf", $headers);

        $pdf->assertOk();
        $raw = base64_decode((string) $pdf->json('pdf_base64'), true);
        $this->assertNotFalse($raw);
        $this->assertStringStartsWith('%PDF', $raw);
    }
}
