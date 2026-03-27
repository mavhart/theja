<?php

namespace App\Http\Controllers;

use App\Models\LacSupplySchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LacScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->input('expiring_days', 7);
        $items = LacSupplySchedule::query()
            ->with(['patient:id,last_name,first_name', 'product:id,brand,model'])
            ->expiringSoon($days)
            ->orderBy('estimated_end_date')
            ->get();

        $data = $items->map(function (LacSupplySchedule $s) {
            $daysRemaining = now()->startOfDay()->diffInDays($s->estimated_end_date->copy()->startOfDay(), false);

            return [
                'id'                 => $s->id,
                'patient_id'         => $s->patient_id,
                'product_id'         => $s->product_id,
                'estimated_end_date' => $s->estimated_end_date?->format('Y-m-d'),
                'days_remaining'     => $daysRemaining,
                'patient_name'       => trim(($s->patient->last_name ?? '').' '.($s->patient->first_name ?? '')),
                'product_name'       => trim(($s->product->brand ?? '').' '.($s->product->model ?? '')),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
