<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class QueryBuilderService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableFilters(string $entity): array
    {
        return match ($entity) {
            'sales' => [
                ['key' => 'product_category', 'label' => 'Categoria prodotto', 'type' => 'select'],
                ['key' => 'supply_type', 'label' => 'Tipo fornitura', 'type' => 'select'],
                ['key' => 'customer_gender', 'label' => 'Genere cliente', 'type' => 'select'],
                ['key' => 'price_range', 'label' => 'Fascia prezzo', 'type' => 'range'],
                ['key' => 'supplier', 'label' => 'Fornitore', 'type' => 'select'],
                ['key' => 'operator', 'label' => 'Operatore', 'type' => 'select'],
                ['key' => 'date_from', 'label' => 'Data da', 'type' => 'date'],
                ['key' => 'date_to', 'label' => 'Data a', 'type' => 'date'],
                ['key' => 'payment_method', 'label' => 'Metodo pagamento', 'type' => 'select'],
                ['key' => 'status', 'label' => 'Stato', 'type' => 'select'],
            ],
            'products' => [
                ['key' => 'category', 'label' => 'Categoria', 'type' => 'select'],
                ['key' => 'supplier', 'label' => 'Fornitore', 'type' => 'select'],
                ['key' => 'brand', 'label' => 'Marchio', 'type' => 'select'],
                ['key' => 'material', 'label' => 'Materiale', 'type' => 'select'],
                ['key' => 'lens_type', 'label' => 'Tipo lenti', 'type' => 'select'],
                ['key' => 'gender', 'label' => 'Genere', 'type' => 'select'],
                ['key' => 'price_range', 'label' => 'Fascia prezzo', 'type' => 'range'],
                ['key' => 'stock_available', 'label' => 'Disponibilità', 'type' => 'select'],
            ],
            'patients' => [
                ['key' => 'age_from', 'label' => 'Età da', 'type' => 'number'],
                ['key' => 'age_to', 'label' => 'Età a', 'type' => 'number'],
                ['key' => 'gender', 'label' => 'Genere', 'type' => 'select'],
                ['key' => 'city', 'label' => 'Città', 'type' => 'select'],
                ['key' => 'uses_lac', 'label' => 'Usa LAC', 'type' => 'boolean'],
                ['key' => 'prescription_expired', 'label' => 'Prescrizione scaduta', 'type' => 'boolean'],
                ['key' => 'inserted_from', 'label' => 'Inserimento da', 'type' => 'date'],
                ['key' => 'inserted_to', 'label' => 'Inserimento a', 'type' => 'date'],
            ],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string> $groupBy
     * @return array<string, mixed>
     */
    public function executeQuery(string $entity, array $filters, array $groupBy, string $chartType): array
    {
        $posId = (string) ($filters['pos_id'] ?? '');
        $group = $groupBy[0] ?? 'none';

        // Risposta "universale" per UI:
        // chart_data: punti {label,value}
        // table: righe {label,value}
        $chartData = [];
        $tableRows = [];

        if ($entity === 'sales') {
            $dateFrom = isset($filters['date_from']) ? (string) $filters['date_from'] : null;
            $dateTo = isset($filters['date_to']) ? (string) $filters['date_to'] : null;
            $operatorId = isset($filters['operator']) ? (string) $filters['operator'] : null;
            $status = isset($filters['status']) ? (string) $filters['status'] : null;
            $supplyType = isset($filters['supply_type']) ? (string) $filters['supply_type'] : null;

            $salesQ = Sale::query()
                ->where('pos_id', $posId)
                ->when($dateFrom, fn ($q) => $q->where('sale_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->where('sale_date', '<=', $dateTo))
                ->when($operatorId, fn ($q) => $q->where('user_id', $operatorId))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($supplyType, fn ($q) => $q->where('type', $supplyType));

            if ($group === 'month' || $group === 'day' || $group === 'week' || $group === 'year') {
                $periodExpr = match ($group) {
                    'day' => "sales.sale_date::date",
                    'week' => "DATE_TRUNC('week', sales.sale_date)::date",
                    'year' => "DATE_TRUNC('year', sales.sale_date)::date",
                    default => "DATE_TRUNC('month', sales.sale_date)::date",
                };

                $rows = $salesQ
                    ->clone()
                    ->select(DB::raw($periodExpr.' as period'), DB::raw('SUM(total_amount) as total'))
                    ->groupBy(DB::raw($periodExpr))
                    ->orderBy('period')
                    ->get();

                $chartData = $rows->map(fn ($r) => [
                    'label' => (string) $r->period,
                    'value' => (float) $r->total,
                ])->values()->all();
            } elseif ($group === 'type') {
                $rows = $salesQ
                    ->clone()
                    ->select('type', DB::raw('SUM(total_amount) as total'))
                    ->groupBy('type')
                    ->orderByDesc('total')
                    ->get();

                $chartData = $rows->map(fn ($r) => [
                    'label' => (string) $r->type,
                    'value' => (float) $r->total,
                ])->values()->all();
            } elseif ($group === 'operator') {
                $rows = $salesQ
                    ->clone()
                    ->select('user_id', DB::raw('SUM(total_amount) as total'))
                    ->groupBy('user_id')
                    ->orderByDesc('total')
                    ->get();

                $chartData = $rows->map(fn ($r) => [
                    'label' => (string) $r->user_id,
                    'value' => (float) $r->total,
                ])->values()->all();
            } elseif ($group === 'payment_method') {
                $paymentMethod = isset($filters['payment_method']) ? (string) $filters['payment_method'] : null;

                $paymentsQ = Payment::query()
                    ->join('sales', 'sales.id', '=', 'payments.sale_id')
                    ->where('sales.pos_id', $posId)
                    ->when($dateFrom, fn ($q) => $q->where('sales.sale_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->where('sales.sale_date', '<=', $dateTo))
                    ->when($operatorId, fn ($q) => $q->where('sales.user_id', $operatorId))
                    ->when($status, fn ($q) => $q->where('sales.status', $status))
                    ->when($supplyType, fn ($q) => $q->where('sales.type', $supplyType))
                    ->when($paymentMethod, fn ($q) => $q->where('payments.method', $paymentMethod));

                $rows = $paymentsQ
                    ->select('payments.method', DB::raw('SUM(payments.amount) as total'))
                    ->groupBy('payments.method')
                    ->orderByDesc('total')
                    ->get();

                $chartData = $rows->map(fn ($r) => [
                    'label' => (string) $r->method,
                    'value' => (float) $r->total,
                ])->values()->all();
            }
        }

        if ($entity === 'products') {
            $category = isset($filters['category']) ? (string) $filters['category'] : null;
            $supplier = isset($filters['supplier']) ? (string) $filters['supplier'] : null;
            $brand = isset($filters['brand']) ? (string) $filters['brand'] : null;

            $rows = InventoryItem::query()
                ->join('products', 'products.id', '=', 'inventory_items.product_id')
                ->where('inventory_items.pos_id', $posId)
                ->when($category, fn ($q) => $q->where('products.category', $category))
                ->when($supplier, fn ($q) => $q->where('products.supplier_id', $supplier))
                ->when($brand, fn ($q) => $q->where('products.brand', $brand))
                ->select('products.category', DB::raw('SUM(inventory_items.quantity_sold) as sold_qty'))
                ->groupBy('products.category')
                ->orderByDesc('sold_qty')
                ->limit(20)
                ->get();

            $chartData = $rows->map(fn ($r) => [
                'label' => (string) $r->category,
                'value' => (float) $r->sold_qty,
            ])->values()->all();
        }

        if ($entity === 'patients') {
            $from = isset($filters['inserted_from']) ? (string) $filters['inserted_from'] : null;
            $to = isset($filters['inserted_to']) ? (string) $filters['inserted_to'] : null;
            $gender = isset($filters['gender']) ? (string) $filters['gender'] : null;

            $q = Patient::query()
                ->where('pos_id', $posId)
                ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from))
                ->when($to, fn ($qq) => $qq->whereDate('created_at', '<=', $to))
                ->when($gender, fn ($qq) => $qq->where('gender', $gender));

            // group per month by default
            $rows = $q
                ->clone()
                ->select(DB::raw("DATE_TRUNC('month', created_at)::date as period"), DB::raw('COUNT(*) as total'))
                ->groupBy(DB::raw("DATE_TRUNC('month', created_at)::date"))
                ->orderBy('period')
                ->get();

            $chartData = $rows->map(fn ($r) => [
                'label' => (string) $r->period,
                'value' => (int) $r->total,
            ])->values()->all();
        }

        $tableRows = $chartData;

        return [
            'chart_data' => $chartData,
            'table_data' => $tableRows,
            'meta' => [
                'entity' => $entity,
                'group_by' => $group,
                'chart_type' => $chartType,
            ],
        ];
    }
}

