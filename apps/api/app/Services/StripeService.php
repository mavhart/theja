<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAddOn;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct(?StripeClient $client = null)
    {
        $this->client = $client ?? new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Crea un customer Stripe e salva stripe_customer_id sull'Organization.
     */
    public function createCustomer(Organization $org): string
    {
        $customer = $this->client->customers->create([
            'name'  => $org->name,
            'email' => $org->billing_email,
            'metadata' => [
                'organization_id' => $org->id,
            ],
        ]);

        $org->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Crea un abbonamento base in Stripe e salva il record in DB.
     */
    public function createSubscription(Organization $org, int $posCount = 1): Subscription
    {
        $customerId = $org->stripe_customer_id ?? $this->createCustomer($org);

        $stripeSubscription = $this->client->subscriptions->create([
            'customer'          => $customerId,
            'trial_period_days' => 14,
            'items'             => [
                [
                    'price'    => config('services.stripe.prices.base_per_pos'),
                    'quantity' => $posCount,
                ],
            ],
            'metadata' => [
                'organization_id' => $org->id,
            ],
        ]);

        $subscription = Subscription::create([
            'organization_id'        => $org->id,
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status'                 => $this->mapStripeStatus($stripeSubscription->status),
            'plan_base_pos_count'    => $posCount,
            'monthly_total'          => 0,
            'trial_ends_at'          => $stripeSubscription->trial_end
                ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end)
                : null,
            'current_period_end'     => \Carbon\Carbon::createFromTimestamp(
                $stripeSubscription->current_period_end
            ),
        ]);

        return $subscription;
    }

    /**
     * Aggiunge un add-on a un abbonamento Stripe e salva il record in DB.
     */
    public function addAddon(
        Subscription $sub,
        string $featureKey,
        int $quantity,
        ?string $posId = null
    ): SubscriptionAddOn {
        $priceId = config("services.stripe.prices.{$featureKey}");

        $item = $this->client->subscriptionItems->create([
            'subscription' => $sub->stripe_subscription_id,
            'price'        => $priceId,
            'quantity'     => $quantity,
        ]);

        $unitPrice = $item->price->unit_amount / 100;

        return SubscriptionAddOn::create([
            'organization_id' => $sub->organization_id,
            'pos_id'          => $posId,
            'feature_key'     => $featureKey,
            'quantity'        => $quantity,
            'stripe_item_id'  => $item->id,
            'unit_price'      => $unitPrice,
            'is_active'       => true,
        ]);
    }

    /**
     * Cancella l'abbonamento su Stripe e aggiorna il DB.
     */
    public function cancelSubscription(Organization $org): void
    {
        $subscription = $org->subscription;

        if (! $subscription || ! $subscription->stripe_subscription_id) {
            return;
        }

        $this->client->subscriptions->cancel($subscription->stripe_subscription_id);

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Aggiorna lo stato dell'abbonamento in DB a partire da un evento webhook Stripe.
     */
    public function syncFromWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $object = $payload['data']['object'] ?? [];

        match ($type) {
            'customer.subscription.updated'  => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted'  => $this->handleSubscriptionDeleted($object),
            'invoice.payment_failed'         => $this->handleInvoicePaymentFailed($object),
            'invoice.payment_succeeded'      => $this->handleInvoicePaymentSucceeded($object),
            default                          => null,
        };
    }

    // ─── Handler privati ──────────────────────────────────────

    private function handleSubscriptionUpdated(array $object): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $object['id'])->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status'             => $this->mapStripeStatus($object['status']),
            'current_period_end' => isset($object['current_period_end'])
                ? \Carbon\Carbon::createFromTimestamp($object['current_period_end'])
                : null,
        ]);
    }

    private function handleSubscriptionDeleted(array $object): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $object['id'])->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    private function handleInvoicePaymentFailed(array $object): void
    {
        $stripeSubId = $object['subscription'] ?? null;

        if (! $stripeSubId) {
            return;
        }

        Subscription::where('stripe_subscription_id', $stripeSubId)
            ->update(['status' => 'past_due']);
    }

    private function handleInvoicePaymentSucceeded(array $object): void
    {
        $stripeSubId = $object['subscription'] ?? null;

        if (! $stripeSubId) {
            return;
        }

        Subscription::where('stripe_subscription_id', $stripeSubId)
            ->where('status', 'past_due')
            ->update(['status' => 'active']);
    }

    /**
     * Mappa lo status Stripe nel nostro enum locale (gestisce 'canceled' → 'cancelled').
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'canceled'   => 'cancelled',
            'trialing'   => 'trialing',
            'active'     => 'active',
            'past_due'   => 'past_due',
            'paused'     => 'paused',
            default      => 'past_due',
        };
    }
}
