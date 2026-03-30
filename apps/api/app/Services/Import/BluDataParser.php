<?php

namespace App\Services\Import;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class BluDataParser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parsePatients(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, [
            'clienti.csv', 'clients.csv', 'customers.csv',
        ]);

        if (! $path) return [];

        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $last = $this->getAny($row, ['cognome', 'surname', 'last_name']);
            $first = $this->getAny($row, ['nome', 'name', 'first_name']);
            $cf = $this->getAny($row, ['cf', 'fiscal_code', 'codicefiscale', 'codice_fiscale', 'codice_fiscale']);
            $date = $this->getAny($row, ['data_nascita', 'datanascita', 'birth_date', 'data nascita']);
            $addr = $this->getAny($row, ['indirizzo', 'address']);
            $note = $this->getAny($row, ['note', 'notes']);

            $phone1 = $this->getAny($row, ['telefono', 'phone', 'telefono1', 'tel1', 'tel']);
            $phone2 = $this->getAny($row, ['telefono2', 'phone2', 'mobile', 'tel2']);

            if (! $cf) {
                return null;
            }

            return [
                'first_name'    => $first,
                'last_name'     => $last,
                'fiscal_code'   => $cf,
                'phone'         => $phone1,
                'phone2'        => $phone2,
                'date_of_birth' => $this->normalizeDate($date),
                'address'       => $addr,
                'notes'         => $note,
            ];
        }, $rows)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parsePrescriptions(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, [
            'optometria.csv', 'prescriptions.csv', 'iride.csv', 'focus.csv',
        ]);

        if (! $path) return [];

        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $cf = $this->getAny($row, ['cf', 'fiscal_code', 'codicefiscale', 'codice_fiscale']);
            $visit = $this->getAny($row, ['data_visita', 'datavisita', 'visit_date', 'data visita']);

            if (! $cf) {
                return null;
            }

            return [
                'fiscal_code'   => $cf,
                'visit_date'    => $this->normalizeDate($visit),
                'od_sphere_far'  => $this->toNullableFloat($this->getAny($row, ['od_sfera_far', 'od_sfera', 'od_sphere', 'od_sphere_far', 'od_sfera_far'])),
                'os_sphere_far'  => $this->toNullableFloat($this->getAny($row, ['os_sfera_far', 'os_sfera', 'os_sphere', 'os_sphere_far', 'os_sfera_far'])),
                'od_cylinder_far'=> $this->toNullableFloat($this->getAny($row, ['od_cilindro_far', 'od_cilindro', 'od_cylinder', 'od_cylinder_far'])),
                'os_cylinder_far'=> $this->toNullableFloat($this->getAny($row, ['os_cilindro_far', 'os_cilindro', 'os_cylinder', 'os_cylinder_far'])),
                'od_axis_far'    => $this->toNullableFloat($this->getAny($row, ['od_asse_far', 'od_asse', 'od_axis', 'od_axis_far'])),
                'os_axis_far'    => $this->toNullableFloat($this->getAny($row, ['os_asse_far', 'os_asse', 'os_axis', 'os_axis_far'])),
            ];
        }, $rows)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseProducts(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, [
            'articoli.csv', 'items.csv', 'products.csv',
        ]);

        if (! $path) return [];

        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $barcode = $this->getAny($row, ['barcode', 'codice_barre', 'codice a barre', 'ean', 'ean13']);
            if (! $barcode) return null;

            $supplier = $this->getAny($row, ['fornitore', 'supplier']);
            $brand = $this->getAny($row, ['marca', 'brand']);
            $model = $this->getAny($row, ['modello', 'model']);

            $salePrice = $this->toNullableFloat($this->getAny($row, ['prezzo_vendita', 'prezzo vendita', 'sale_price', 'price_sale', 'prezzo']));

            return [
                'barcode'    => (string) $barcode,
                'supplier'   => $supplier,
                'brand'      => $brand,
                'model'      => $model,
                'sale_price' => $salePrice,
            ];
        }, $rows)));
    }

    private function resolveTablePath(string $fileOrDir, array $candidates): ?string
    {
        if (is_dir($fileOrDir)) {
            foreach ($candidates as $name) {
                $p = rtrim($fileOrDir, '\\/').DIRECTORY_SEPARATOR.$name;
                if (file_exists($p)) return $p;
            }

            // fallback: cerca per suffisso
            $lower = strtolower($fileOrDir);
            foreach ($candidates as $name) {
                $p = rtrim($fileOrDir, '\\/').DIRECTORY_SEPARATOR.$name;
                if (file_exists($p)) return $p;
            }
            return null;
        }

        // caso file singolo: usiamo solo se corrisponde a uno dei candidate (e assumiamo tabella unica)
        foreach ($candidates as $name) {
            if (str_ends_with(strtolower($fileOrDir), strtolower($name))) {
                return $fileOrDir;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readCsvAssoc(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) return [];

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $data);
                continue;
            }

            if ($data === [null]) continue;

            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = isset($data[$idx]) ? (string) $data[$idx] : null;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = str_replace([' ', '-', '.'], '_', $h);
        $h = preg_replace('/[^a-z0-9_]/', '', $h) ?: $h;
        return $h;
    }

    private function getAny(array $row, array $keys): mixed
    {
        foreach ($keys as $k) {
            $nk = $this->normalizeHeader((string) $k);
            if (array_key_exists($nk, $row)) {
                $v = $row[$nk];
                if ($v !== null && trim((string) $v) !== '') {
                    return $v;
                }
            }
        }

        return null;
    }

    private function normalizeDate(mixed $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim((string) $raw);
        if ($s === '') return null;

        // supporta YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = array_map('intval', explode('/', $s));
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $s)) {
            [$d, $m, $y] = array_map('intval', explode('-', $s));
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }

        // fallback: prova parse strtotime
        $ts = strtotime($s);
        if ($ts === false) return null;

        return date('Y-m-d', $ts);
    }

    private function toNullableFloat(mixed $raw): ?float
    {
        if ($raw === null) return null;
        $s = trim((string) $raw);
        if ($s === '') return null;

        // gestisce virgola decimale
        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) return null;

        return (float) $s;
    }
}

