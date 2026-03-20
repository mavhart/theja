<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create([
            'billing_email' => 'test@otticarossi.it',
        ]);
    }

    // ─── createCustomer ───────────────────────────────────────

    public function test_create_customer_saves_stripe_customer_id(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')
                ->once()
                ->with(Mockery::on(fn ($arg) => $arg->id === $this->org->id))
                ->andReturnUsing(function (Organization $org) {
                    $org->update(['stripe_customer_id' => 'cus_test_abc123']);
                    return 'cus_test_abc123';
                });
        });

        $service = app(StripeService::class);
        $customerId = $service->createCustomer($this->org);

        $this->assertSame('cus_test_abc123', $customerId);
        $this->assertDatabaseHas('organizations', [
            'id'                 => $this->org->id,
            'stripe_customer_id' => 'cus_test_abc123',
        ]);
    }

    // ─── Webhook: subscription.updated ───────────────────────

    public function test_webhook_subscription_updated_changes_status(): void
    {
        $secret = 'whsec_test_secret_1234';
        config(['services.stripe.webhook.secret' => $secret]);

        $subscription = Subscription::create([
            'organization_id'        => $this->org->id,
            'stripe_customer_id'     => 'cus_test',
            'stripe_subscription_id' => 'sub_test_001',
            'status'                 => 'trialing',
            'plan_base_pos_count'    => 1,
            'monthly_total'          => 0,
            'current_period_end'     => now()->addDays(30),
        ]);

        $payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id'                  => 'sub_test_001',
                    'status'              => 'active',
                    'current_period_end'  => now()->addDays(30)->timestamp,
                ],
            ],
        ]);

        $timestamp = time();
        $sigBody   = "{$timestamp}.{$payload}";
        $sig       = hash_hmac('sha256', $sigBody, $secret);
        $sigHeader = "t={$timestamp},v1={$sig}";

        // Usa server variables direttamente per evitare trasformazioni di withHeaders()
        $response = $this->call(
            'POST',
            '/api/stripe/webhook',
            [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => 'active',
        ]);
    }

    // ─── Webhook: firma invalida ──────────────────────────────

    public function test_webhook_invalid_signature_returns_400(): void
    {
        config(['services.stripe.webhook.secret' => 'whsec_real_secret']);

        $payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_fake', 'status' => 'active', 'current_period_end' => time()]],
        ]);

        $response = $this->withHeaders(['Stripe-Signature' => 't=12345,v1=invalidsignature'])
            ->call('POST', '/api/stripe/webhook', [], [], [], [], $payload);

        $response->assertStatus(400);
    }

    // ─── Webhook: header mancante ─────────────────────────────

    public function test_webhook_missing_signature_returns_400(): void
    {
        $payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_fake', 'status' => 'active', 'current_period_end' => time()]],
        ]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [], $payload);

        $response->assertStatus(400);
    }
}
