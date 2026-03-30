<?php

namespace App\Http\Controllers;

use App\Helpers\PermissionHelper;
use App\Models\Organization;
use App\Models\PointOfSale;
use App\Services\QueryBuilderService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request, ReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'string'],
        ]);

        $pos = $this->resolvePos($request);
        $from = Carbon::parse((string) $data['from'])->startOfDay();
        $to = Carbon::parse((string) $data['to'])->endOfDay();
        $groupBy = isset($data['group_by']) && $data['group_by'] ? (string) $data['group_by'] : 'month';

        return response()->json([
            'data' => [
                'sales_summary' => $service->getSalesSummary($pos, $from, $to),
                'revenue_by_period' => $service->getRevenueByPeriod($pos, $from, $to, $groupBy),
            ],
        ]);
    }

    public function inventory(Request $request, ReportService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json([
            'data' => $service->getInventoryReport($pos),
        ]);
    }

    public function patients(Request $request, ReportService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json([
            'data' => $service->getPatientReport($pos),
        ]);
    }

    public function revenue(Request $request, ReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'string'],
        ]);

        $pos = $this->resolvePos($request);
        $from = Carbon::parse((string) $data['from'])->startOfDay();
        $to = Carbon::parse((string) $data['to'])->endOfDay();
        $groupBy = isset($data['group_by']) && $data['group_by'] ? (string) $data['group_by'] : 'month';

        return response()->json([
            'data' => $service->getRevenueByPeriod($pos, $from, $to, $groupBy),
        ]);
    }

    public function topProducts(Request $request, ReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $pos = $this->resolvePos($request);
        $from = Carbon::parse((string) $data['from'])->startOfDay();
        $to = Carbon::parse((string) $data['to'])->endOfDay();
        $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

        return response()->json([
            'data' => $service->getTopProducts($pos, $from, $to, $limit),
        ]);
    }

    public function orgAggregate(Request $request, ReportService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);
        $this->assertOrgOwner($request, $pos);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        /** @var Organization $org */
        $org = $pos->organization;
        $from = Carbon::parse((string) $data['from'])->startOfDay();
        $to = Carbon::parse((string) $data['to'])->endOfDay();

        return response()->json([
            'data' => $service->getOrgAggregate($org, $from, $to),
        ]);
    }

    public function queryBuilder(Request $request, QueryBuilderService $service): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string', 'in:sales,products,patients'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable'],
            'chart_type' => ['nullable', 'string', 'in:pie,bar,line,table'],
        ]);

        $pos = $this->resolvePos($request);
        $entity = (string) $data['entity'];

        $filters = $data['filters'] ?? [];
        $filters['pos_id'] = $pos->id;

        $groupByRaw = $data['group_by'] ?? 'none';
        $groupBy = is_array($groupByRaw) ? $groupByRaw : [$groupByRaw];
        $chartType = isset($data['chart_type']) ? (string) $data['chart_type'] : 'table';

        $availableFilters = $service->getAvailableFilters($entity);
        $result = $service->executeQuery($entity, $filters, $groupBy, $chartType);

        return response()->json([
            'data' => [
                'available_filters' => $availableFilters,
                'result' => $result,
            ],
        ]);
    }

    private function assertOrgOwner(Request $request, PointOfSale $pos): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        $allowed = PermissionHelper::userCan($user, 'reports.view_org_aggregate', $pos->id);
        abort_unless($allowed, 403, 'Non autorizzato.');
    }

    private function resolvePos(Request $request): PointOfSale
    {
        $id = (string) ($request->query('pos_id') ?: $request->user()?->current_pos_id);
        abort_if(empty($id), 422, 'pos_id mancante.');

        return PointOfSale::query()->findOrFail($id);
    }
}

