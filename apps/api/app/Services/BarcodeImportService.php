<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BarcodeImportService
{
    /**
     * @return array<string, mixed>|null
     */
    public function lookupBarcode(string $barcode): ?array
    {
        $product = Product::query()->with('supplier')->where('barcode', $barcode)->first();
        if ($product) {
            return [
                'source'  => 'tenant_products',
                'product' => $product->toArray(),
            ];
        }

        // Fallback listino di sistema opzionale (se tabella presente)
        try {
            $row = DB::table('system_price_lists')->where('barcode', $barcode)->first();
            if ($row) {
                return [
                    'source'  => 'system_price_list',
                    'product' => (array) $row,
                ];
            }
        } catch (\Throwable) {
            // Nessun listino di sistema configurato nel tenant/env corrente.
        }

        return null;
    }

    /**
     * @param  array<string, string>  $columnMapping
     * @return array<string, mixed>
     */
    public function importFromCsv(UploadedFile $file, array $columnMapping): array
    {
        $path = $file->getRealPath();
        $h = fopen($path, 'rb');
        if (! $h) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Impossibile leggere file CSV']];
        }

        $header = fgetcsv($h);
        if (! is_array($header)) {
            fclose($h);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Header CSV mancante']];
        }

        $index = [];
        foreach ($header as $i => $col) {
            $index[trim((string) $col)] = $i;
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        while (($row = fgetcsv($h)) !== false) {
            $payload = [];
            foreach ($columnMapping as $field => $csvColName) {
                if (! isset($index[$csvColName])) {
                    continue;
                }
                $payload[$field] = $row[$index[$csvColName]] ?? null;
            }
            if (! isset($payload['barcode']) || trim((string) $payload['barcode']) === '') {
                $skipped++;
                continue;
            }
            $imported++;
        }
        fclose($h);

        return compact('imported', 'skipped', 'errors');
    }
}
