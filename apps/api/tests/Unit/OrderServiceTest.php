<?php

namespace Tests\Unit;

use App\Models\CommunicationLog;
use App\Models\Organization;
use App\Models\Order;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\User;
use App\Services\CommunicationService;
use App\Services\OrderService;
use App\Support\TenantClinicalSchema;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private PointOfSale $pos;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->pos = PointOfSale::factory()->create([
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->forOrganization($this->org)->create();

        $schema = TenantClinicalSchema::schemaNameForOrganizationId($this->org->id);
        DB::statement('SET search_path TO "'.$schema.'", public');
    }

    protected function tearDown(): void
    {
        DB::statement('SET search_path TO public');
        parent::tearDown();
    }

    private function createPatient(): Patient
    {
        return Patient::create([
            'organization_id' => $this->org->id,
            'pos_id' => $this->pos->id,
            'last_name' => 'Rossi',
            'first_name' => 'Mario',
            'fiscal_code' => 'RSSMRA80E15H501Z',
            'mobile' => '3200000000',
            'gdpr_marketing_consent' => true,
        ]);
    }

    #[Test]
    public function generateJobCode_has_expected_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));

        $service = new OrderService(new CommunicationService());
        $code = $service->generateJobCode($this->pos);

        $this->assertSame('2603-0001', $code);
    }

    #[Test]
    public function updateStatus_ready_creates_communication_log_trigger_order_ready(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));

        $patient = $this->createPatient();

        $order = Order::create([
            'pos_id' => $this->pos->id,
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'status' => 'sent',
            'order_date' => '2026-03-15',
            'job_code' => null,
            'total_amount' => 0,
        ]);

        $service = new OrderService(new CommunicationService());

        $service->updateStatus($order, 'ready');

        $log = CommunicationLog::query()
            ->where('trigger', 'order_ready')
            ->where('patient_id', $patient->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('sent', (string) ($log->status ?? ''));
        $this->assertSame('sms', (string) ($log->type ?? ''));
    }
}

