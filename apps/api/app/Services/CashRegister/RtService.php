<?php

namespace App\Services\CashRegister;

use App\Models\Sale;
use Illuminate\Support\Facades\Log;

class RtService
{
    public function sendDocument(Sale $sale, string $type): bool
    {
        $enabled = filter_var((string) env('CASH_REGISTER_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $ip = (string) env('CASH_REGISTER_IP', '');
        $port = (int) env('CASH_REGISTER_PORT', 9100);
        $env = app()->environment();

        // In dev/staging: logga e "simula" l'invio.
        if (! $enabled || in_array($env, ['local', 'staging', 'testing'], true)) {
            Log::info('[RT] sendDocument stub', [
                'sale_id' => $sale->id,
                'type'    => $type,
                'env'     => $env,
                'ip'      => $ip,
                'port'    => $port,
            ]);

            return true;
        }

        // Placeholder implementazione "reale": invio TCP semplice (protocollo standard da definire in Fase 7).
        if ($ip === '') {
            Log::warning('[RT] sendDocument: CASH_REGISTER_IP mancante', ['sale_id' => $sale->id, 'type' => $type]);
            return false;
        }

        $timestamp = $sale->updated_at?->toIso8601String() ?? now()->toIso8601String();

        $xml = <<<XML
<rt_document>
  <type>{$type}</type>
  <sale_id>{$sale->id}</sale_id>
  <timestamp>{$timestamp}</timestamp>
</rt_document>
XML;

        try {
            $fp = @fsockopen($ip, $port, $errno, $errstr, 2.0);
            if (! $fp) {
                Log::warning('[RT] fsockopen fallita', ['errno' => $errno, 'errstr' => $errstr]);
                return false;
            }

            fwrite($fp, $xml);
            fclose($fp);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[RT] sendDocument errore', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

