<?php

namespace App\Services\Import;

class CsvParser
{
    /**
     * Standard CSV Theja: supporto due modalità:
     * 1) directory contenente: patients.csv, prescriptions.csv, products.csv
     * 2) singolo file CSV con header che permette di dedurre l'entità.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parsePatients(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, ['patients.csv', 'patient.csv']);
        if (! $path) return [];
        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $last = $this->getAny($row, ['last_name', 'cognome', 'surname']);
            $first = $this->getAny($row, ['first_name', 'nome', 'name']);
            $cf = $this->getAny($row, ['fiscal_code', 'cf', 'codice_fiscale', 'codicefiscale']);
            $phone = $this->getAny($row, ['phone', 'telefono']);
            $phone2 = $this->getAny($row, ['phone2', 'telefono2', 'mobile', 'cell']);
            $dob = $this->getAny($row, ['date_of_birth', 'data_nascita', 'birth_date', 'datanascita']);
            $address = $this->getAny($row, ['address', 'indirizzo', 'billing_address']);
            $notes = $this->getAny($row, ['notes', 'note']);

            if (! $cf) return null;

            return [
                'first_name'    => $first,
                'last_name'     => $last,
                'fiscal_code'   => $cf,
                'phone'         => $phone,
                'phone2'        => $phone2,
                'date_of_birth' => $this->normalizeDate($dob),
                'address'       => $address,
                'notes'         => $notes,
            ];
        }, $rows)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parsePrescriptions(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, ['prescriptions.csv', 'prescrizioni.csv', 'optometria.csv']);
        if (! $path) return [];
        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $cf = $this->getAny($row, ['fiscal_code', 'cf', 'codice_fiscale', 'codicefiscale']);
            $visit = $this->getAny($row, ['visit_date', 'data_visita', 'datavisita', 'data_visita']);

            if (! $cf) return null;

            return [
                'fiscal_code'    => $cf,
                'visit_date'     => $this->normalizeDate($visit),
                'od_sphere_far'   => $this->toNullableFloat($this->getAny($row, ['od_sphere_far', 'od_sfera_far', 'od_sfera'])),
                'os_sphere_far'   => $this->toNullableFloat($this->getAny($row, ['os_sphere_far', 'os_sfera_far', 'os_sfera'])),
                'od_cylinder_far' => $this->toNullableFloat($this->getAny($row, ['od_cylinder_far', 'od_cilindro_far', 'od_cilindro'])),
                'os_cylinder_far' => $this->toNullableFloat($this->getAny($row, ['os_cylinder_far', 'os_cilindro_far', 'os_cilindro'])),
                'od_axis_far'     => $this->toNullableFloat($this->getAny($row, ['od_axis_far', 'od_asse_far', 'od_asse'])),
                'os_axis_far'     => $this->toNullableFloat($this->getAny($row, ['os_axis_far', 'os_asse_far', 'os_asse'])),
            ];
        }, $rows)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseProducts(string $fileOrDir): array
    {
        $path = $this->resolveTablePath($fileOrDir, ['products.csv', 'articoli.csv', 'products_theja.csv']);
        if (! $path) return [];
        $rows = $this->readCsvAssoc($path);

        return array_values(array_filter(array_map(function ($row) {
            $barcode = $this->getAny($row, ['barcode', 'ean', 'ean13']);
            if (! $barcode) return null;

            $supplier = $this->getAny($row, ['supplier', 'fornitore']);
            $brand = $this->getAny($row, ['brand', 'marca']);
            $model = $this->getAny($row, ['model', 'modello']);
            $salePrice = $this->toNullableFloat($this->getAny($row, ['sale_price', 'prezzo_vendita', 'prezzo vendita', 'price']));

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
            return null;
        }

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

        // supporta ; o , come separatore
        $firstLine = fgets($handle);
        if ($firstLine === false) return [];
        $delim = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        while (($data = fgetcsv($handle, 0, $delim)) !== false) {
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

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = array_map('intval', explode('/', $s));
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }

        $ts = strtotime($s);
        if ($ts === false) return null;

        return date('Y-m-d', $ts);
    }

    private function toNullableFloat(mixed $raw): ?float
    {
        if ($raw === null) return null;
        $s = trim((string) $raw);
        if ($s === '') return null;
        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) return null;
        return (float) $s;
    }
}

