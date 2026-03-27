<?php

namespace App\Services\CashRegister\Providers;

use Illuminate\Support\Facades\Log;

class LogRtProvider implements RtProviderInterface
{
    public function sendScontrino(array $data): array
    {
        Log::info('[LogRtProvider] sendScontrino', $data);

        return [
            'ok' => true,
            'provider' => 'log',
            'message_id' => 'log-'.uniqid(),
            'payload' => $data,
        ];
    }

    public function sendChiusura(array $data): array
    {
        Log::info('[LogRtProvider] sendChiusura', $data);

        return [
            'ok' => true,
            'provider' => 'log',
            'message_id' => 'log-chiusura-'.uniqid(),
            'payload' => $data,
        ];
    }

    public function getStatus(): array
    {
        return [
            'ok' => true,
            'provider' => 'log',
            'status' => 'ready',
        ];
    }
}

