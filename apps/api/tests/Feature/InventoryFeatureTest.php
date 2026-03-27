<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\LacSupplySchedule;
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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryFeatureTest extends TestCase
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
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $this->org = Organization::factory()->create();
        $this->pos1 = PointOfSale::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'POS A',
        ]);
        $this->pos2 = PointOfSale::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'POS B',
        ]);
        $this->user = User::factory()->create([
            'organization_id' => $this->org->id,
            'email' => 'inventory-test@theja.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $role = Role::where('name', 'org_owner')->first();
        UserPosRole::create([
            'user_id' => $this->user->id,
            'pos_id' => $this->pos1->id,
            'role_id' => $role->id,
            'can_see_purchase_prices' => true,
        ]);
        UserPosRole::create([
            'user_id' => $this->user->id,
            'pos_id' => $this->pos2->id,
            'role_id' => $role->id,
            'can_see_purchase_prices' => true,
        ]);
    }

    private function authHeaders(): array
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'inventory-test@theja.test',
            'password' => 'password',
        ]);
        $token = $login->json('token');
        if ($login->json('requires_pos_selection')) {
            $this->postJson('/api/auth/select-pos', ['pos_id' => $this->pos1->id], [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->assertOk();
        }

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    public function test_product_crud_with_encrypted_purchase_price(): void
    {
        $h = $this->authHeaders();
        $supplier = $this->postJson('/api/suppliers', [
            'type' => 'azienda',
            'company_name' => 'Lux Supplier',
            'categories' => ['montature'],
        ], $h)->assertOk();

        $product = $this->postJson('/api/products', [
            'supplier_id' => $supplier->json('data.id'),
            'category' => 'montatura',
            'brand' => 'Ray',
            'model' => 'R1',
            'purchase_price' => 45.90,
        ], $h)->assertOk();

        $id = $product->json('data.id');
        $this->assertSame('45.90', (string) $product->json('data.purchase_price'));

        $schema = TenantClinicalSchema::schemaNameForOrganizationId($this->org->id);
        $row = DB::selectOne("SELECT purchase_price FROM \"{$schema}\".products WHERE id = ?", [$id]);
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('45.90', (string) $row->purchase_price);
    }

    public function test_stock_movement_carico_with_ddt_updates_inventory(): void
    {
        $h = $this->authHeaders();
        $product = $this->postJson('/api/products', [
            'category' => 'liquido_accessorio',
            'brand' => 'Opti',
            'model' => 'Drop',
        ], $h)->assertOk();

        $movement = $this->postJson('/api/stock-movements', [
            'pos_id' => $this->pos1->id,
            'product_id' => $product->json('data.id'),
            'type' => 'carico',
            'quantity' => 10,
            'ddt_number' => 'D-001',
            'ddt_date' => '2026-03-27',
        ], $h)->assertOk();

        $this->assertSame('carico', $movement->json('data.type'));
        $item = InventoryItem::where('pos_id', $this->pos1->id)
            ->where('product_id', $product->json('data.id'))
            ->first();
        $this->assertNotNull($item);
        $this->assertSame(10, $item->quantity);
    }

    public function test_transfer_flow_request_accept_generate_ddt_and_complete(): void
    {
        $h = $this->authHeaders();
        $product = $this->postJson('/api/products', [
            'category' => 'montatura',
            'brand' => 'BrandX',
            'model' => 'M10',
        ], $h)->assertOk();
        $pid = $product->json('data.id');

        $this->postJson('/api/inventory/update-stock', [
            'pos_id' => $this->pos1->id,
            'product_id' => $pid,
            'quantity' => 20,
        ], $h)->assertOk();

        $req = $this->postJson('/api/stock-transfers/request', [
            'from_pos_id' => $this->pos1->id,
            'to_pos_id' => $this->pos2->id,
            'product_id' => $pid,
            'quantity' => 5,
        ], $h)->assertOk();
        $tid = $req->json('data.id');

        $accepted = $this->postJson("/api/stock-transfers/{$tid}/accept", [], $h)->assertOk();
        $this->assertSame('accepted', $accepted->json('data.status'));
        $this->assertNotNull($accepted->json('data.ddt_number'));
        Storage::disk('local')->assertExists((string) $accepted->json('data.ddt_pdf_path'));

        $completed = $this->postJson("/api/stock-transfers/{$tid}/complete", [], $h)->assertOk();
        $this->assertSame('completed', $completed->json('data.status'));
    }

    public function test_lac_supply_schedule_end_date_calculation(): void
    {
        $h = $this->authHeaders();
        $patient = $this->postJson('/api/patients', [
            'pos_id' => $this->pos1->id,
            'last_name' => 'Lac',
            'first_name' => 'Sched',
        ], $h)->assertCreated();
        $product = $this->postJson('/api/products', [
            'category' => 'lente_contatto',
            'brand' => 'Acme',
            'model' => 'Monthly',
        ], $h)->assertOk();

        $schedule = LacSupplySchedule::create([
            'patient_id' => $patient->json('data.id'),
            'pos_id' => $this->pos1->id,
            'product_id' => $product->json('data.id'),
            'supply_date' => '2026-03-01',
            'quantity' => 2,
            'estimated_duration_days' => 30,
            'estimated_end_date' => '2026-03-31',
            'created_at' => now(),
        ]);

        $this->assertSame('2026-03-31', $schedule->calculateEndDate());
    }
}
