<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Order;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Models\UserPosRole;
use App\Support\TenantClinicalSchema;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class Phase9CriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function setTenantSearchPath(Organization $org): void
    {
        $schema = TenantClinicalSchema::schemaNameForOrganizationId($org->id);
        DB::statement('SET search_path TO '.$schema.', public');
    }

    private function authHeaders(string $email, string $password): array
    {
        $login = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $token = (string) $login->json('token');

        if ($login->json('requires_pos_selection')) {
            $this->fail('Test helper richiede pos selection: specifica un POS single access.');
        }

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ];
    }

    private function createOrgWithPosAndUser(string $email, string $orgName = 'Org'): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);

        $pos = PointOfSale::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $user = User::factory()->forOrganization($org)->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $role = Role::where('name', 'org_owner')->firstOrFail();
        UserPosRole::create([
            'user_id' => $user->id,
            'pos_id' => $pos->id,
            'role_id' => $role->id,
            'can_see_purchase_prices' => false,
        ]);

        return [$org, $pos, $user];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        DB::statement('SET search_path TO public');
        parent::tearDown();
    }

    #[Test]
    public function flusso_completo_vendita_acconto_e_saldo_decrementa_magazzino_e_segnala_fully_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));

        [$org, $pos, $user] = $this->createOrgWithPosAndUser('sale-flow@theja.test');
        $this->setTenantSearchPath($org);

        $p1 = Product::create([
            'organization_id' => $org->id,
            'category' => 'montatura',
            'is_polarized' => false,
            'is_ce' => false,
            'attributes' => [],
            'vat_rate' => 22,
            'is_active' => true,
        ]);
        $p2 = Product::create([
            'organization_id' => $org->id,
            'category' => 'accessorio',
            'is_polarized' => false,
            'is_ce' => false,
            'attributes' => [],
            'vat_rate' => 22,
            'is_active' => true,
        ]);

        InventoryItem::create(['pos_id' => $pos->id, 'product_id' => $p1->id, 'quantity' => 10]);
        InventoryItem::create(['pos_id' => $pos->id, 'product_id' => $p2->id, 'quantity' => 10]);

        $headers = $this->authHeaders('sale-flow@theja.test', 'password');

        $patient = $this->postJson('/api/patients', [
            'pos_id' => $pos->id,
            'last_name' => 'Rossi',
            'first_name' => 'Mario',
            'fiscal_code' => 'RSSMRA80E15H501Z',
            'mobile' => '3200000000',
            'communication_sms' => true,
        ], $headers)->assertCreated();

        $patientId = $patient->json('data.id');

        $sale = $this->postJson('/api/sales', [
            'pos_id' => $pos->id,
            'patient_id' => $patientId,
            'type' => 'generico',
            'sale_date' => '2026-03-10',
            'items' => [
                [
                    'product_id' => $p1->id,
                    'description' => 'Prodotto 1',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'discount_percent' => 0,
                    'vat_rate' => 22,
                ],
                [
                    'product_id' => $p2->id,
                    'description' => 'Prodotto 2',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'discount_percent' => 0,
                    'vat_rate' => 22,
                ],
            ],
        ], $headers)->assertCreated();

        $saleId = $sale->json('data.id');

        $this->postJson('/api/sales/'.$saleId.'/payments', [
            'amount' => 80,
            'method' => 'contanti',
            'payment_date' => '2026-03-10',
        ], $headers)->assertOk();

        $this->postJson('/api/sales/'.$saleId.'/payments', [
            'amount' => 120,
            'method' => 'contanti',
            'payment_date' => '2026-03-10',
        ], $headers)->assertOk();

        $summary = $this->getJson('/api/sales/'.$saleId.'/payment-summary', $headers)->assertOk();
        $this->assertTrue((bool) $summary->json('data.is_fully_paid'));

        // Magazzino: 1+1 prodotti venduti -> -1 per ciascun prodotto
        $this->setTenantSearchPath($org);
        $inv1 = InventoryItem::query()->where('pos_id', $pos->id)->where('product_id', $p1->id)->firstOrFail();
        $inv2 = InventoryItem::query()->where('pos_id', $pos->id)->where('product_id', $p2->id)->firstOrFail();
        $this->assertSame(9, (int) $inv1->quantity);
        $this->assertSame(9, (int) $inv2->quantity);
    }

    #[Test]
    public function flusso_fattura_crea_emetti_e_verifica_xml_fatturapa_e_progressivo_univoco(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-20 10:00:00'));

        [$org, $pos, $user] = $this->createOrgWithPosAndUser('invoice-flow@theja.test');
        $this->setTenantSearchPath($org);

        $p1 = Product::create([
            'organization_id' => $org->id,
            'category' => 'montatura',
            'is_polarized' => false,
            'is_ce' => false,
            'attributes' => [],
            'vat_rate' => 22,
            'is_active' => true,
        ]);
        InventoryItem::create(['pos_id' => $pos->id, 'product_id' => $p1->id, 'quantity' => 10]);

        $headers = $this->authHeaders('invoice-flow@theja.test', 'password');

        $patient = $this->postJson('/api/patients', [
            'pos_id' => $pos->id,
            'last_name' => 'Bianchi',
            'first_name' => 'Anna',
            'fiscal_code' => 'BNCHNA90A41F205X',
            'mobile' => '3330000000',
            'communication_sms' => true,
        ], $headers)->assertCreated();

        $patientId = $patient->json('data.id');

        $sale1 = $this->postJson('/api/sales', [
            'pos_id' => $pos->id,
            'patient_id' => $patientId,
            'type' => 'generico',
            'sale_date' => '2026-03-20',
            'items' => [
                [
                    'product_id' => $p1->id,
                    'description' => 'Prodotto',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_percent' => 0,
                    'vat_rate' => 22,
                ],
            ],
        ], $headers)->assertCreated();

        $saleId1 = $sale1->json('data.id');

        $invoice1 = $this->postJson('/api/invoices', [
            'sale_id' => $saleId1,
            'type' => 'fattura_pa',
            'payment_method' => 'MP01',
            'payment_terms' => null,
        ], $headers)->assertCreated();

        $invoiceId1 = $invoice1->json('data.id');
        $invoiceNo1 = $invoice1->json('data.invoice_number');

        $this->postJson('/api/invoices/'.$invoiceId1.'/issue', [], $headers)->assertOk();

        $xmlRes1 = $this->getJson('/api/invoices/'.$invoiceId1.'/xml', $headers)->assertOk();
        $xml1 = $xmlRes1->json('xml');

        $dom = new \DOMDocument();
        $ok = @$dom->loadXML($xml1);
        $this->assertTrue($ok, 'XML non valido');

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('p', 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2');
        $this->assertGreaterThan(0, $xpath->query('//p:DatiGeneraliDocumento/p:Numero')->length);
        $this->assertGreaterThan(0, $xpath->query('//p:CessionarioCommittente/p:CodiceFiscale')->length);
        $this->assertGreaterThan(0, $xpath->query('//p:DettaglioLinee')->length);

        // 2a fattura: progressivo univoco
        $sale2 = $this->postJson('/api/sales', [
            'pos_id' => $pos->id,
            'patient_id' => $patientId,
            'type' => 'generico',
            'sale_date' => '2026-03-20',
            'items' => [
                [
                    'product_id' => $p1->id,
                    'description' => 'Prodotto',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'discount_percent' => 0,
                    'vat_rate' => 22,
                ],
            ],
        ], $headers)->assertCreated();

        $saleId2 = $sale2->json('data.id');

        $invoice2 = $this->postJson('/api/invoices', [
            'sale_id' => $saleId2,
            'type' => 'fattura_pa',
            'payment_method' => 'MP01',
        ], $headers)->assertCreated();

        $invoiceNo2 = $invoice2->json('data.invoice_number');
        $this->assertNotSame($invoiceNo1, $invoiceNo2);
    }

    #[Test]
    public function flusso_trasferimento_pos_a_richiesta_pos_b_accetta_ddt_e_completa_aggiorna_magazzino(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 10:00:00'));

        // Same org, two POS
        $org = Organization::factory()->create(['name' => 'Org Transfer']);
        $posA = PointOfSale::factory()->create(['organization_id' => $org->id, 'is_active' => true, 'name' => 'POS A']);
        $posB = PointOfSale::factory()->create(['organization_id' => $org->id, 'is_active' => true, 'name' => 'POS B']);

        $role = Role::where('name', 'org_owner')->firstOrFail();

        $userA = User::factory()->forOrganization($org)->create([
            'email' => 'transfer-a@theja.test',
            'password' => Hash::make('password'),
        ]);
        UserPosRole::create([
            'user_id' => $userA->id,
            'pos_id' => $posA->id,
            'role_id' => $role->id,
            'can_see_purchase_prices' => false,
        ]);

        $userB = User::factory()->forOrganization($org)->create([
            'email' => 'transfer-b@theja.test',
            'password' => Hash::make('password'),
        ]);
        UserPosRole::create([
            'user_id' => $userB->id,
            'pos_id' => $posB->id,
            'role_id' => $role->id,
            'can_see_purchase_prices' => false,
        ]);

        $this->setTenantSearchPath($org);

        $product = Product::create([
            'organization_id' => $org->id,
            'category' => 'montatura',
            'is_polarized' => false,
            'is_ce' => false,
            'attributes' => [],
            'vat_rate' => 22,
            'is_active' => true,
        ]);

        InventoryItem::create(['pos_id' => $posA->id, 'product_id' => $product->id, 'quantity' => 10]);
        InventoryItem::create(['pos_id' => $posB->id, 'product_id' => $product->id, 'quantity' => 0]);

        $headersA = $this->authHeaders('transfer-a@theja.test', 'password');
        $headersB = $this->authHeaders('transfer-b@theja.test', 'password');

        $qty = 3;
        $req = $this->postJson('/api/stock-transfers/request', [
            'from_pos_id' => $posA->id,
            'to_pos_id' => $posB->id,
            'product_id' => $product->id,
            'quantity' => $qty,
        ], $headersA)->assertOk();

        $transferId = $req->json('data.id');

        $accept = $this->postJson('/api/stock-transfers/'.$transferId.'/accept', [], $headersB)->assertOk();
        $this->assertNotEmpty((string) $accept->json('data.ddt_number'));

        $this->postJson('/api/stock-transfers/'.$transferId.'/complete', [], $headersA)->assertOk();

        $this->setTenantSearchPath($org);
        $invA = InventoryItem::query()->where('pos_id', $posA->id)->where('product_id', $product->id)->firstOrFail();
        $invB = InventoryItem::query()->where('pos_id', $posB->id)->where('product_id', $product->id)->firstOrFail();

        $this->assertSame(10 - $qty, (int) $invA->quantity);
        $this->assertSame(0 + $qty, (int) $invB->quantity);
        $this->assertSame(0, (int) $invA->quantity_reserved);
    }

    #[Test]
    public function isolamento_tenant_paziente_org1_non_visibile_da_org2(): void
    {
        [$org1, $pos1, $user1] = $this->createOrgWithPosAndUser('tenant-a@theja.test', 'Org1');
        [$org2, $pos2, $user2] = $this->createOrgWithPosAndUser('tenant-b@theja.test', 'Org2');

        $headers1 = $this->authHeaders('tenant-a@theja.test', 'password');
        $headers2 = $this->authHeaders('tenant-b@theja.test', 'password');

        $this->postJson('/api/patients', [
            'pos_id' => $pos1->id,
            'last_name' => 'Neri',
            'first_name' => 'Luca',
            'fiscal_code' => 'NERLCU80A41F205Y',
            'mobile' => '3201111111',
            'communication_sms' => true,
        ], $headers1)->assertCreated();

        $listOrg2 = $this->getJson('/api/patients?q=Neri', $headers2)->assertOk();
        $this->assertCount(0, $listOrg2->json('data'));
    }
}

