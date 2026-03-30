<?php

namespace App\Services;

use App\Models\PointOfSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAnalysisService
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeTrends(PointOfSale $pos): array
    {
        $this->assertFeatureEnabled($pos);

        $from = now()->subMonths(6)->startOfMonth();
        $to = now();
        $revenue = $this->reportService->getRevenueByPeriod($pos, $from, $to, 'month');
        $salesSummary = $this->reportService->getSalesSummary($pos, $from, $to);

        return $this->callClaudeJson(
            endpoint: 'trends',
            pos: $pos,
            promptData: [
                'revenue_by_month' => $revenue,
                'sales_summary' => $salesSummary,
            ],
            systemInstruction: 'Sei un analista di performance per ottici. Fornisci output esclusivamente aggregato e senza dati personali.',
            instruction:
                'Analizza trend vendite e composizione (per tipo e per metodo). Restituisci JSON con chiavi narrative (string), data (oggetto), chart_data (array).',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forecastReorders(PointOfSale $pos): array
    {
        $this->assertFeatureEnabled($pos);

        $inventory = $this->reportService->getInventoryReport($pos);

        return $this->callClaudeJson(
            endpoint: 'forecast-reorders',
            pos: $pos,
            promptData: [
                'inventory' => $inventory,
            ],
            systemInstruction: 'Sei un assistente supply-chain per ottici. Usa solo dati aggregati e non inventare valori non presenti.',
            instruction:
                'Prevedi quali categorie/prodotti richiedono riordino nel prossimo periodo e suggerisci azioni. Restituisci JSON con narrative, data e chart_data.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeRevenue(PointOfSale $pos): array
    {
        $this->assertFeatureEnabled($pos);

        $from = now()->subMonths(3)->startOfMonth();
        $to = now();
        $revenueMonth = $this->reportService->getRevenueByPeriod($pos, $from, $to, 'month');
        $topProducts = $this->reportService->getTopProducts($pos, $from, $to, 10);

        return $this->callClaudeJson(
            endpoint: 'revenue-analysis',
            pos: $pos,
            promptData: [
                'revenue_by_month' => $revenueMonth,
                'top_products' => $topProducts,
            ],
            systemInstruction: 'Sei un analista finanziario per attività retail. Usa solo aggregati.',
            instruction:
                'Analizza crescita fatturato e mix prodotti. Restituisci JSON con narrative, data e chart_data.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function findOpportunities(PointOfSale $pos): array
    {
        $this->assertFeatureEnabled($pos);

        $patient = $this->reportService->getPatientReport($pos);
        $inventory = $this->reportService->getInventoryReport($pos);

        return $this->callClaudeJson(
            endpoint: 'opportunities',
            pos: $pos,
            promptData: [
                'patients' => $patient,
                'inventory' => $inventory,
            ],
            systemInstruction: 'Sei un consulente commerciale per ottici. Lavora con soli aggregati e suggerisci azioni.',
            instruction:
                'Identifica opportunità: pazienti senza rinnovo LAC, prescrizioni scadute, prodotti fermi. Restituisci JSON con narrative, data e chart_data.',
        );
    }

    private function assertFeatureEnabled(PointOfSale $pos): void
    {
        if (! $pos->ai_analysis_enabled) {
            abort(403, 'AI Analysis non attiva per questo POS.');
        }
    }

    /**
     * Chiama Claude e prova a parse-are un JSON valido.
     *
     * @param array<string, mixed> $promptData
     * @return array<string, mixed>
     */
    private function callClaudeJson(
        string $endpoint,
        PointOfSale $pos,
        array $promptData,
        string $systemInstruction,
        string $instruction,
    ): array {
        $apiKey = (string) env('ANTHROPIC_API_KEY', '');

        Log::info('[AI] Claude analysis call', [
            'endpoint' => $endpoint,
            'pos_id' => $pos->id,
            'prompt_keys' => array_keys($promptData),
            'prompt_size' => mb_strlen(json_encode($promptData)),
        ]);

        if (empty($apiKey)) {
            return [
                'narrative' => 'AI Analysis non configurata: manca ANTHROPIC_API_KEY.',
                'data' => $promptData,
                'chart_data' => [],
            ];
        }

        $system = $systemInstruction;
        $userText = $instruction."\n\nDATI_AGGREGATI:\n".json_encode($promptData, JSON_UNESCAPED_UNICODE);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(45)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 1000,
            'messages' => [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => $userText]]],
            ],
            'system' => $system,
        ]);

        if (! $response->ok()) {
            return [
                'narrative' => 'Errore chiamata Claude (verifica ANTHROPIC_API_KEY e rete).',
                'data' => $promptData,
                'chart_data' => [],
            ];
        }

        $payload = $response->json();
        $text = (string) ($payload['content'][0]['text'] ?? '');

        $parsed = $this->parseJsonFromText($text);
        if (is_array($parsed) && isset($parsed['narrative'])) {
            // Ripulizia minima per evitare tipi inattesi.
            if (! isset($parsed['chart_data'])) $parsed['chart_data'] = [];
            $parsed['chart_data'] = $this->normalizeChartData($parsed['chart_data']);
            if (! isset($parsed['data'])) $parsed['data'] = [];
            return $parsed;
        }

        return [
            'narrative' => Str::limit($text, 400) ?: 'Claude non ha restituito un JSON valido.',
            'data' => $promptData,
            'chart_data' => [],
        ];
    }

    /**
     * Normalizza chart_data in forma: [{label: string, value: number}, ...]
     *
     * Claude può rispondere con shape diversi (es. {labels, values}, [{name, y}], ecc.).
     *
     * @param mixed $chartDataRaw
     * @return array<int, array{label: string, value: float}>
     */
    private function normalizeChartData(mixed $chartDataRaw): array
    {
        if (! is_array($chartDataRaw)) {
            return [];
        }

        // Caso: {labels:[], values:[]} o simili
        if (isset($chartDataRaw['labels']) && isset($chartDataRaw['values'])
            && is_array($chartDataRaw['labels']) && is_array($chartDataRaw['values'])) {
            $out = [];
            $labels = array_values($chartDataRaw['labels']);
            $values = array_values($chartDataRaw['values']);
            $count = min(count($labels), count($values));
            for ($i = 0; $i < $count; $i++) {
                $out[] = [
                    'label' => (string) $labels[$i],
                    'value' => (float) $values[$i],
                ];
            }
            return $out;
        }

        $out = [];

        foreach ($chartDataRaw as $k => $item) {
            // Caso array associativo { "label": value }
            if (! is_array($item) && is_numeric($item)) {
                $out[] = ['label' => (string) $k, 'value' => (float) $item];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            // Caso [{label, value}]
            $label = $item['label'] ?? null;
            $value = $item['value'] ?? null;
            if ($label !== null && $value !== null && is_numeric($value)) {
                $out[] = ['label' => (string) $label, 'value' => (float) $value];
                continue;
            }

            // Caso [{name, y}] oppure [{x,y}]
            $label = $item['name'] ?? $item['x'] ?? null;
            $value = $item['y'] ?? $item['value'] ?? null;
            if ($label !== null && $value !== null && is_numeric($value)) {
                $out[] = ['label' => (string) $label, 'value' => (float) $value];
                continue;
            }

            // Caso array tuple [label, value]
            if (array_is_list($item) && count($item) >= 2 && is_numeric($item[1])) {
                $out[] = ['label' => (string) $item[0], 'value' => (float) $item[1]];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        if (empty($text)) return null;

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }
}

