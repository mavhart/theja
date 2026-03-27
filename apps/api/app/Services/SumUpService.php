<?php

namespace App\Services;

use App\Models\PointOfSale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumUpService
{
    public function __construct(private readonly PointOfSale $pos)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayment(float $amount, string $currency = 'EUR', string $description = ''): array
    {
        if ($this->isMockMode()) {
            return [
                'id' => 'sumup-mock-'.uniqid(),
                'status' => 'PENDING',
                'amount' => round($amount, 2),
                'currency' => $currency,
                'description' => $description,
            ];
        }

        $response = Http::withToken((string) $this->pos->sumup_api_key)
            ->post(rtrim((string) env('SUMUP_API_URL', 'https://api.sumup.com/v0.1'), '/').'/checkouts', [
                'amount' => round($amount, 2),
                'currency' => $currency,
                'description' => $description,
            ]);

        return $response->json() ?: ['status' => 'ERROR'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if ($this->isMockMode()) {
            return [
                'id' => $paymentId,
                'status' => 'SUCCESSFUL',
            ];
        }

        $response = Http::withToken((string) $this->pos->sumup_api_key)
            ->get(rtrim((string) env('SUMUP_API_URL', 'https://api.sumup.com/v0.1'), '/').'/checkouts/'.$paymentId);

        return $response->json() ?: ['status' => 'ERROR'];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(string $paymentId, float $amount): array
    {
        if ($this->isMockMode()) {
            return [
                'id' => 'sumup-refund-'.uniqid(),
                'payment_id' => $paymentId,
                'amount' => round($amount, 2),
                'status' => 'SUCCESSFUL',
            ];
        }

        $response = Http::withToken((string) $this->pos->sumup_api_key)
            ->post(rtrim((string) env('SUMUP_API_URL', 'https://api.sumup.com/v0.1'), '/').'/checkouts/'.$paymentId.'/refund', [
                'amount' => round($amount, 2),
            ]);

        return $response->json() ?: ['status' => 'ERROR'];
    }

    private function isMockMode(): bool
    {
        return app()->environment(['local', 'testing', 'staging']) || empty($this->pos->sumup_api_key);
    }
}

