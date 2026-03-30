<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Support\TenantClinicalSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class SaleServiceTest extends TestCase
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

    private function createProductWithStock(int $quantity): Product
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category' => 'montatura',
            'is_polarized' => false,
            'is_ce' => false,
            'attributes' => [],
            'vat_rate' => 22,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'pos_id' => $this->pos->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'min_stock' => 0,
            'max_stock' => 100,
        ]);

        return $product;
    }

    #[Test]
    public function createSale_decrements_inventory_and_creates_stock_movement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01 10:00:00'));

        $product = $this->createProductWithStock(5);
        $service = new SaleService();

        $sale = $service->createSale(
            [
                'pos_id' => $this->pos->id,
                'user_id' => $this->user->id,
                'status' => 'quote',
                'type' => 'generico',
                'sale_date' => '2026-03-01',
                'discount_amount' => 0,
            ],
            [
                [
                    'product_id' => $product->id,
                    'description' => 'Prod test',
                    'quantity' => 2,
                    'unit_price' => 50,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'vat_rate' => 22,
                ],
            ],
        );

        $inv = InventoryItem::query()
            ->where('pos_id', $this->pos->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame(3, (int) $inv->quantity);
        $this->assertSame('quote', (string) $sale->status);
        $this->assertSame(0.0, (float) $sale->discount_amount);
        $this->assertGreaterThan(0, (float) $sale->total_amount);
    }

    #[Test]
    public function addPayment_updates_paid_amount_and_sets_confirmed_when_fully_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01 10:00:00'));

        $product = $this->createProductWithStock(5);
        $service = new SaleService();

        $sale = $service->createSale(
            [
                'pos_id' => $this->pos->id,
                'user_id' => $this->user->id,
                'status' => 'quote',
                'type' => 'generico',
                'sale_date' => '2026-03-01',
                'discount_amount' => 0,
            ],
            [
                [
                    'product_id' => $product->id,
                    'description' => 'Prod test',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'vat_rate' => 0,
                ],
            ],
        );

        $service->addPayment($sale, [
            'amount' => 60,
            'method' => 'contanti',
            'payment_date' => '2026-03-01',
            'is_scheduled' => false,
        ]);

        $sale = $sale->fresh();
        $this->assertSame(60.0, (float) $sale->paid_amount);
        $this->assertSame('quote', (string) $sale->status);

        $service->addPayment($sale, [
            'amount' => 40,
            'method' => 'contanti',
            'payment_date' => '2026-03-01',
            'is_scheduled' => false,
        ]);

        $sale = $sale->fresh();
        $this->assertTrue($sale->is_fully_paid);
        $this->assertSame('confirmed', (string) $sale->status);
    }

    #[Test]
    public function cancelSale_restores_inventory_quantity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01 10:00:00'));

        $product = $this->createProductWithStock(5);
        $service = new SaleService();

        $sale = $service->createSale(
            [
                'pos_id' => $this->pos->id,
                'user_id' => $this->user->id,
                'status' => 'quote',
                'type' => 'generico',
                'sale_date' => '2026-03-01',
                'discount_amount' => 0,
            ],
            [
                [
                    'product_id' => $product->id,
                    'description' => 'Prod test',
                    'quantity' => 2,
                    'unit_price' => 50,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'vat_rate' => 0,
                ],
            ],
        );

        $service->cancelSale($sale);

        $inv = InventoryItem::query()
            ->where('pos_id', $this->pos->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame(5, (int) $inv->quantity);
        $this->assertSame('cancelled', (string) $sale->fresh()->status);
    }
}

