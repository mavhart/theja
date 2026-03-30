<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\LacSupplySchedule;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Prescription;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Ritorna riepilogo vendite aggregato (no dati personali).
     *
     * @return array<string, mixed>
     */
    public function getSalesSummary(PointOfSale $pos, Carbon $from, Carbon $to): array
    {
        $salesQ = Sale::query()
            ->where('pos_id', $pos->id)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['confirmed', 'delivered']);

        $total = (float) $salesQ->clone()->sum('total_amount');

        $byTypeRows = $salesQ
            ->clone()
            ->select('type', DB::raw('SUM(total_amount) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        $byOperatorRows = $salesQ
            ->clone()
            ->select('user_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get();

        $paymentRows = Payment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('sales.pos_id', $pos->id)
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $userNameById = DB::table('users')
            ->whereIn('id', $byOperatorRows->pluck('user_id'))
            ->pluck('name', 'id');

        return [
            'total_amount' => $total,
            'by_type' => $byTypeRows->map(fn ($r) => [
                'type' => (string) $r->type,
                'total' => (float) $r->total,
            ])->values(),
            'by_operator' => $byOperatorRows->map(fn ($r) => [
                'user_id' => (string) $r->user_id,
                'user_name' => $userNameById[$r->user_id] ?? null,
                'total' => (float) $r->total,
            ])->values(),
            'by_payment_method' => $paymentRows->map(fn ($r) => [
                'method' => (string) $r->method,
                'total' => (float) $r->total,
            ])->values(),
        ];
    }

    /**
     * Report magazzino aggregato: sotto scorta, rotazione, valore totale.
     *
     * @return array<string, mixed>
     */
    public function getInventoryReport(PointOfSale $pos): array
    {
        $belowStockRows = InventoryItem::query()
            ->where('pos_id', $pos->id)
            ->whereColumn('quantity', '<', 'min_stock')
            ->with(['product'])
            ->get();

        $belowStock = $belowStockRows->map(function (InventoryItem $item) {
            return [
                'product_id' => (string) $item->product_id,
                'product_category' => $item->product?->category ?? null,
                'product_brand' => $item->product?->brand ?? null,
                'product_model' => $item->product?->model ?? null,
                'quantity' => (int) $item->quantity,
                'min_stock' => (int) $item->min_stock,
                'max_stock' => (int) $item->max_stock,
            ];
        })->values()->all();

        $rotationRows = InventoryItem::query()
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->where('inventory_items.pos_id', $pos->id)
            ->select('products.category', DB::raw('SUM(inventory_items.quantity_sold) as sold_qty'))
            ->groupBy('products.category')
            ->orderByDesc('sold_qty')
            ->limit(10)
            ->get();

        $rotationByCategory = $rotationRows->map(fn ($r) => [
            'category' => (string) $r->category,
            'sold_qty' => (int) $r->sold_qty,
        ])->values()->all();

        // Valore magazzino: somma (quantita * purchase_price) calcolata lato PHP
        // perché purchase_price è cifrato e non aggregabile lato SQL.
        $allItems = InventoryItem::query()
            ->where('pos_id', $pos->id)
            ->with(['product'])
            ->get();

        $inventoryValueTotal = $allItems->reduce(function (float $carry, InventoryItem $item) {
            $purchasePrice = (float) ($item->product?->purchase_price ?? 0);
            return $carry + ((int) $item->quantity * $purchasePrice);
        }, 0.0);

        return [
            'below_stock' => $belowStock,
            'rotation_by_category' => $rotationByCategory,
            'inventory_value_total' => $inventoryValueTotal,
        ];
    }

    /**
     * Report pazienti: nuovi pazienti, prescrizioni scadute, LAC attivi.
     *
     * @return array<string, mixed>
     */
    public function getPatientReport(PointOfSale $pos): array
    {
        $from = now()->subMonths(6)->startOfMonth();
        $to = now()->endOfDay();

        $newPatientsRows = Patient::query()
            ->where('pos_id', $pos->id)
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw("DATE_TRUNC('month', created_at)::date as period"), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw("DATE_TRUNC('month', created_at)::date"))
            ->orderBy('period')
            ->get();

        $newPatients = $newPatientsRows->map(fn ($r) => [
            'period' => (string) $r->period,
            'value' => (int) $r->total,
        ])->values()->all();

        $expiredPrescriptions = Prescription::query()
            ->where('pos_id', $pos->id)
            ->whereNotNull('next_recall_at')
            ->whereDate('next_recall_at', '<', now()->toDateString())
            ->count();

        $lacActiveCount = LacSupplySchedule::query()
            ->where('pos_id', $pos->id)
            ->whereDate('estimated_end_date', '>=', now()->toDateString())
            ->distinct('patient_id')
            ->count('patient_id');

        return [
            'new_patients_by_month' => $newPatients,
            'prescriptions_expired_count' => (int) $expiredPrescriptions,
            'lac_active_count' => (int) $lacActiveCount,
        ];
    }

    /**
     * Fatturato raggruppato per periodo.
     *
     * @return array<string, mixed>
     */
    public function getRevenueByPeriod(PointOfSale $pos, Carbon $from, Carbon $to, string $groupBy = 'month'): array
    {
        $groupBy = strtolower($groupBy);

        $periodExpr = match ($groupBy) {
            'day' => "sales.sale_date::date",
            'week' => "DATE_TRUNC('week', sales.sale_date)::date",
            'year' => "DATE_TRUNC('year', sales.sale_date)::date",
            default => "DATE_TRUNC('month', sales.sale_date)::date",
        };

        $rows = Sale::query()
            ->where('pos_id', $pos->id)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['confirmed', 'delivered'])
            ->select(
                DB::raw($periodExpr . ' as period'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy(DB::raw($periodExpr))
            ->orderBy('period')
            ->get();

        $chartData = $rows->map(function ($r) use ($groupBy) {
            $period = Carbon::parse((string) $r->period);
            $label = match ($groupBy) {
                'day' => $period->format('d/m'),
                'week' => 'W'.$period->format('W'),
                'year' => $period->format('Y'),
                default => $period->format('M Y'),
            };

            return [
                'label' => $label,
                'value' => (float) $r->total,
            ];
        })->values()->all();

        return [
            'group_by' => $groupBy,
            'chart_data' => $chartData,
        ];
    }

    /**
     * Prodotti più venduti nel periodo.
     *
     * @return array<string, mixed>
     */
    public function getTopProducts(PointOfSale $pos, Carbon $from, Carbon $to, int $limit = 10): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select(
                'products.id as product_id',
                'products.category',
                'products.brand',
                'products.model',
                DB::raw('SUM(sale_items.quantity) as qty'),
                DB::raw('SUM(sale_items.total) as revenue')
            )
            ->where('sales.pos_id', $pos->id)
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('sales.status', ['confirmed', 'delivered'])
            ->groupBy('products.id', 'products.category', 'products.brand', 'products.model')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $items = $rows->map(fn ($r) => [
            'product_id' => (string) $r->product_id,
            'category' => $r->category,
            'label' => trim((string) ($r->brand ?? '').' '.($r->model ?? '')) ?: (string) $r->product_id,
            'qty' => (int) $r->qty,
            'revenue' => (float) $r->revenue,
        ])->values()->all();

        return [
            'items' => $items,
        ];
    }

    /**
     * Aggregato multi-POS a livello organization (solo org_owner in UI).
     *
     * @return array<string, mixed>
     */
    public function getOrgAggregate(Organization $org, Carbon $from, Carbon $to): array
    {
        $posIds = DB::table('points_of_sale')
            ->where('organization_id', $org->id)
            ->pluck('id');

        $totalSales = Sale::query()
            ->whereIn('pos_id', $posIds)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['confirmed', 'delivered'])
            ->sum('total_amount');

        return [
            'organization_id' => (string) $org->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_amount' => (float) $totalSales,
        ];
    }
}

