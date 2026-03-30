<?php

namespace App\Services\Import;

use App\Models\Organization;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Prescription;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRunner
{
    public function __construct(
        private readonly BluDataParser $bluDataParser,
        private readonly CsvParser $csvParser,
    ) {
    }

    /**
     * @param array{dry_run: bool, source: 'bludata'|'csv', file: string} $opts
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function run(array $opts): array
    {
        $dryRun = (bool) ($opts['dry_run'] ?? false);
        $source = (string) ($opts['source'] ?? 'bludata');
        $file = (string) ($opts['file'] ?? '');

        $parser = match ($source) {
            'bludata' => $this->bluDataParser,
            'csv' => $this->csvParser,
            default => $this->bluDataParser,
        };

        $orgs = Organization::query()->where('is_active', true)->get();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($orgs as $org) {
            $pos = $this->resolveActivePos($org);
            if (! $pos) {
                Log::warning('[ImportRunner] Org senza POS attivo', ['org_id' => $org->id]);
                continue;
            }

            $this->setTenantSearchPath($org->id);

            $patients = $parser->parsePatients($file);
            $prescriptions = $parser->parsePrescriptions($file);
            $products = $parser->parseProducts($file);

            $imported += $this->importPatients($patients, $pos->id, $dryRun, $org->id, $skipped, $errors);
            $imported += $this->importPrescriptions($prescriptions, $pos->id, $dryRun, $org->id, $skipped, $errors);
            $imported += $this->importProducts($products, $pos->id, $dryRun, $org->id, $skipped, $errors);
        }

        return [
            'imported' => (int) $imported,
            'skipped'  => (int) $skipped,
            'errors'   => (int) $errors,
        ];
    }

    private function resolveActivePos(Organization $org): ?PointOfSale
    {
        return $org->pointsOfSale()->where('is_active', true)->first();
    }

    private function setTenantSearchPath(string $organizationId): void
    {
        $schemaName = 'tenant_' . str_replace('-', '', $organizationId);
        DB::statement("SET search_path TO {$schemaName}, public");
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importPatients(array $rows, string $posId, bool $dryRun, string $orgId, int &$skipped, int &$errors): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $fiscal = $row['fiscal_code'] ?? null;
            if (! $fiscal) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $posId, $dryRun, $orgId, $fiscal, &$count) {
                    if ($dryRun) {
                        $count++;
                        return;
                    }

                    // Preferiamo update/insert basato su CF.
                    $patient = Patient::query()->where('fiscal_code', (string) $fiscal)->first();
                    $payload = [
                        'organization_id' => $orgId,
                        'pos_id'          => $posId,
                        'first_name'     => (string) ($row['first_name'] ?? ''),
                        'last_name'      => (string) ($row['last_name'] ?? ''),
                        'phone'          => (string) ($row['phone'] ?? null),
                        'phone2'         => (string) ($row['phone2'] ?? null),
                        'date_of_birth'  => $row['date_of_birth'] ?? null,
                        'address'        => $row['address'] ?? null,
                        'notes'          => $row['notes'] ?? null,
                        'is_active'      => true,
                    ];

                    if ($patient) {
                        $patient->update($payload);
                    } else {
                        Patient::create($payload + ['fiscal_code' => (string) $fiscal]);
                    }

                    $count++;
                });
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[ImportRunner] importPatients record failed', [
                    'org_id' => $orgId,
                    'pos_id' => $posId,
                    'fiscal_code' => (string) $fiscal,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importPrescriptions(array $rows, string $posId, bool $dryRun, string $orgId, int &$skipped, int &$errors): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $fiscal = $row['fiscal_code'] ?? null;
            $visitDate = $row['visit_date'] ?? null;
            if (! $fiscal || ! $visitDate) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $posId, $dryRun, $orgId, $fiscal, $visitDate, &$count) {
                    if ($dryRun) {
                        $count++;
                        return;
                    }

                    $patient = Patient::query()
                        ->where('fiscal_code', (string) $fiscal)
                        ->first();

                    if (! $patient) {
                        throw new \RuntimeException('Patient not found for CF: '.$fiscal);
                    }

                    $payload = [
                        'pos_id' => $posId,
                        'patient_id' => $patient->id,
                        'visit_date' => $visitDate,
                        'is_international' => false,
                        'od_sphere_far' => $row['od_sphere_far'] ?? null,
                        'os_sphere_far' => $row['os_sphere_far'] ?? null,
                        'od_cylinder_far' => $row['od_cylinder_far'] ?? null,
                        'os_cylinder_far' => $row['os_cylinder_far'] ?? null,
                        'od_axis_far' => $row['od_axis_far'] ?? null,
                        'os_axis_far' => $row['os_axis_far'] ?? null,
                    ];

                    // upsert basato su patient+pos+visit_date
                    $existing = Prescription::query()
                        ->where('patient_id', $patient->id)
                        ->where('pos_id', $posId)
                        ->whereDate('visit_date', $visitDate)
                        ->first();

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        Prescription::create($payload);
                    }

                    $count++;
                });
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[ImportRunner] importPrescriptions record failed', [
                    'org_id' => $orgId,
                    'pos_id' => $posId,
                    'fiscal_code' => (string) $fiscal,
                    'visit_date' => $visitDate,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importProducts(array $rows, string $posId, bool $dryRun, string $orgId, int &$skipped, int &$errors): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $barcode = $row['barcode'] ?? null;
            if (! $barcode) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $posId, $dryRun, $orgId, $barcode, &$count) {
                    if ($dryRun) {
                        $count++;
                        return;
                    }

                    $supplierName = $row['supplier'] ?? null;
                    $supplierId = null;
                    if ($supplierName) {
                        $supplier = Supplier::query()
                            ->where('organization_id', $orgId)
                            ->where('company_name', (string) $supplierName)
                            ->first();

                        if ($supplier) {
                            $supplierId = $supplier->id;
                        } else {
                            $supplierId = Supplier::create([
                                'organization_id' => $orgId,
                                'type' => 'fornitore',
                                'company_name' => (string) $supplierName,
                                'is_active' => true,
                            ])->id;
                        }
                    }

                    $salePrice = $row['sale_price'] ?? null;

                    $existing = Product::query()
                        ->where('organization_id', $orgId)
                        ->where('barcode', (string) $barcode)
                        ->first();

                    $payload = [
                        'organization_id' => $orgId,
                        'supplier_id' => $supplierId,
                        'category' => 'montatura',
                        'barcode' => (string) $barcode,
                        'brand' => $row['brand'] ?? null,
                        'model' => $row['model'] ?? null,
                        'sale_price' => $salePrice,
                        'vat_rate' => 22,
                        'is_active' => true,
                        'attributes' => [],
                    ];

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        Product::create($payload);
                    }

                    // Nota: non creiamo righe inventory_items (richieste non specificate).

                    $count++;
                });
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[ImportRunner] importProducts record failed', [
                    'org_id' => $orgId,
                    'pos_id' => $posId,
                    'barcode' => (string) $barcode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}

