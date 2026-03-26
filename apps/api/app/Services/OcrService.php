<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OcrService
{
    /**
     * Estrae valori optometrici da immagine ricetta (base64 grezzo o data URL).
     *
     * @return array<string, mixed>
     */
    public function parsePrescrizione(string $imageBase64): array
    {
        $key = config('services.openai.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('OPENAI_API_KEY non configurata.');
        }

        $trimmed = trim($imageBase64);
        if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $trimmed, $m)) {
            $b64 = (string) (preg_replace('#^data:image/[^;]+;base64,#', '', $trimmed) ?? '');
            $mime = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
            $dataUrl = 'data:image/'.$mime.';base64,'.$b64;
        } else {
            $dataUrl = 'data:image/jpeg;base64,'.$trimmed;
        }

        $prompt = <<<'PROMPT'
Analizza questa immagine di una ricetta oculistica o prescrizione.
Estrai SOLO i valori numerici/testuali richiesti e restituisci ESCLUSIVAMENTE un oggetto JSON valido, senza markdown, senza testo fuori dal JSON.

Schema JSON richiesto (usa null se il campo non è leggibile):
{
  "od_sphere_far": number|null,
  "os_sphere_far": number|null,
  "od_cylinder_far": number|null,
  "os_cylinder_far": number|null,
  "od_axis_far": number|null,
  "os_axis_far": number|null,
  "od_addition_far": number|null,
  "os_addition_far": number|null,
  "confidence": "high"|"medium"|"low"
}

I numeri diottrie usano il punto come separatore decimale. confidence indica la qualità complessiva dell'estrazione.
PROMPT;

        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o',
                'temperature' => 0.1,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url' => $dataUrl,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI OCR fallita', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Servizio OCR non disponibile.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content)) {
            throw new RuntimeException('Risposta OCR non valida.');
        }

        $json = $this->extractJsonObject($content);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Impossibile interpretare il JSON OCR.');
        }

        return $this->normalizeOcrPayload($decoded);
    }

    private function extractJsonObject(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content) ?? $content;
            $content = preg_replace('/```\s*$/', '', $content) ?? $content;
        }

        return trim($content);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalizeOcrPayload(array $decoded): array
    {
        $keys = [
            'od_sphere_far', 'os_sphere_far',
            'od_cylinder_far', 'os_cylinder_far',
            'od_axis_far', 'os_axis_far',
            'od_addition_far', 'os_addition_far',
        ];

        $out = [];
        foreach ($keys as $k) {
            $v = $decoded[$k] ?? null;
            if ($v === null || $v === '') {
                $out[$k] = null;
            } elseif (is_numeric($v)) {
                $out[$k] = $k === 'od_axis_far' || $k === 'os_axis_far'
                    ? (int) round((float) $v)
                    : round((float) $v, 2);
            } else {
                $out[$k] = null;
            }
        }

        $conf = $decoded['confidence'] ?? 'low';
        $out['confidence'] = in_array($conf, ['high', 'medium', 'low'], true) ? $conf : 'low';

        return $out;
    }
}
