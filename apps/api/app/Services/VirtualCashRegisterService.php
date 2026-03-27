<?php

namespace App\Services;

use App\Models\CashRegisterSession;
use App\Models\FiscalReceipt;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashRegister\RtProviderFactory;

class VirtualCashRegisterService
{
    public function __construct(private readonly RtProviderFactory $providerFactory)
    {
    }

    public function openSession(PointOfSale $pos, User $user, float $openingAmount): CashRegisterSession
    {
        $current = $this->getCurrentSession($pos);
        if ($current) {
            return $current;
        }

        return CashRegisterSession::create([
            'pos_id' => $pos->id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => round($openingAmount, 2),
            'status' => 'open',
        ]);
    }

    public function closeSession(CashRegisterSession $session, float $closingAmount): CashRegisterSession
    {
        if (! $session->isOpen()) {
            return $session;
        }

        $provider = $this->providerFactory->make($session->pointOfSale);
        $provider->sendChiusura([
            'session_id' => $session->id,
            'pos_id' => $session->pos_id,
            'closed_at' => now()->toIso8601String(),
        ]);

        $session->closing_amount = round($closingAmount, 2);
        $session->closed_at = now();
        $session->status = 'closed';
        $session->save();

        return $session->fresh();
    }

    public function sendFiscalDocument(Sale $sale, string $type): FiscalReceipt
    {
        $sale->loadMissing(['items', 'payments', 'pointOfSale']);

        $pos = $sale->pointOfSale;
        if (! $pos) {
            abort(422, 'POS non valido per la vendita.');
        }

        $session = $this->getCurrentSession($pos);
        $provider = $this->providerFactory->make($pos);
        $receiptNumber = $this->generateReceiptNumber($pos);
        $vatBreakdown = $this->buildVatBreakdown($sale);
        $paymentMethod = $sale->payments->first()?->method ?? 'altro';

        $payload = [
            'receipt_number' => $receiptNumber,
            'receipt_date' => now()->format('Y-m-d'),
            'type' => $type,
            'total_amount' => (float) $sale->total_amount,
            'vat_breakdown' => $vatBreakdown,
            'payment_method' => $paymentMethod,
            'sale_id' => $sale->id,
            'pos_id' => $pos->id,
        ];

        $response = $provider->sendScontrino($payload);

        $status = ! empty($response['ok']) ? 'sent' : 'error';

        $receipt = FiscalReceipt::create([
            'pos_id' => $pos->id,
            'sale_id' => $sale->id,
            'cash_register_session_id' => $session?->id,
            'receipt_number' => $receiptNumber,
            'receipt_date' => now()->toDateString(),
            'type' => $type,
            'total_amount' => round((float) $sale->total_amount, 2),
            'vat_breakdown' => $vatBreakdown,
            'payment_method' => $paymentMethod,
            'rt_provider' => $pos->rt_provider ?: env('RT_PROVIDER', 'log'),
            'rt_response' => $response,
            'rt_sent_at' => now(),
            'ade_transmitted_at' => ! empty($response['ok']) ? now() : null,
            'status' => $status,
            'error_message' => $status === 'error' ? ((string) ($response['error'] ?? 'Errore provider RT')) : null,
        ]);

        if ($session && $session->isOpen()) {
            $session->total_sales = round((float) $session->total_sales + (float) $sale->total_amount, 2);
            if (in_array($paymentMethod, ['contanti', 'cash'], true)) {
                $session->total_cash = round((float) $session->total_cash + (float) $sale->total_amount, 2);
            } elseif (in_array($paymentMethod, ['carta', 'card'], true)) {
                $session->total_card = round((float) $session->total_card + (float) $sale->total_amount, 2);
            } else {
                $session->total_other = round((float) $session->total_other + (float) $sale->total_amount, 2);
            }
            $session->save();
        }

        return $receipt;
    }

    public function getCurrentSession(PointOfSale $pos): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('pos_id', $pos->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeCurrentSession(PointOfSale $pos): array
    {
        $session = $this->getCurrentSession($pos);
        if (! $session) {
            return ['session' => null, 'receipts' => []];
        }

        $receipts = FiscalReceipt::query()
            ->where('cash_register_session_id', $session->id)
            ->with('sale')
            ->latest('receipt_date')
            ->limit(20)
            ->get();

        return [
            'session' => $session,
            'receipts' => $receipts,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function buildVatBreakdown(Sale $sale): array
    {
        $out = [];
        foreach ($sale->items as $item) {
            $rate = number_format((float) $item->vat_rate, 2, '.', '');
            $out[$rate] = ($out[$rate] ?? 0) + (float) $item->total;
        }

        foreach ($out as $k => $v) {
            $out[$k] = round($v, 2);
        }

        return $out;
    }

    private function generateReceiptNumber(PointOfSale $pos): string
    {
        $today = now()->format('Ymd');
        $count = FiscalReceipt::query()
            ->where('pos_id', $pos->id)
            ->whereDate('receipt_date', now()->toDateString())
            ->count() + 1;

        return $today.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}

