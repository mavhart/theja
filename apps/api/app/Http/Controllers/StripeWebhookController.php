<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeService $stripeService)
    {
    }

    /**
     * POST /api/stripe/webhook
     * Route SENZA middleware auth — Stripe non invia token.
     */
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook.secret');

        if (! $sigHeader) {
            return response('Missing Stripe-Signature header', 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret,
                config('services.stripe.webhook.tolerance', 300)
            );
        } catch (SignatureVerificationException) {
            return response('Invalid webhook signature', 400);
        } catch (\UnexpectedValueException) {
            return response('Invalid payload', 400);
        }

        $this->stripeService->syncFromWebhook($event->toArray());

        return response('OK', 200);
    }
}
