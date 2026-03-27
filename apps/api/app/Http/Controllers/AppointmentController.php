<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\PointOfSale;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'user_id' => ['nullable'],
            'pos_id' => ['nullable', 'uuid'],
        ]);

        $q = Appointment::query()->with(['patient', 'user', 'order', 'sale'])->orderBy('start_at');
        if ($request->filled('pos_id')) $q->where('pos_id', $request->string('pos_id'));
        if ($request->filled('type')) $q->where('type', $request->string('type'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('user_id')) $q->where('user_id', $request->input('user_id'));
        if ($request->filled('date_from')) $q->where('start_at', '>=', Carbon::parse((string) $request->string('date_from'))->startOfDay());
        if ($request->filled('date_to')) $q->where('start_at', '<=', Carbon::parse((string) $request->string('date_to'))->endOfDay());

        return AppointmentResource::collection($q->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request, AppointmentService $service): AppointmentResource
    {
        $data = $request->validate([
            'pos_id' => ['required', 'uuid', 'exists:points_of_sale,id'],
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['nullable', 'in:visita_optometrica,prova_lac,consegna_ordine,ritiro_riparazione,generico'],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:scheduled,confirmed,completed,cancelled,no_show'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'duration_minutes' => ['nullable', 'integer', 'in:15,30,45,60,90'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],
        ]);

        $apt = $service->create($data);

        return (new AppointmentResource($apt->load(['patient', 'user', 'order', 'sale'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load(['patient', 'user', 'order', 'sale']));
    }

    public function update(Request $request, Appointment $appointment, AppointmentService $service): AppointmentResource
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'user_id' => ['sometimes', 'exists:users,id'],
            'type' => ['sometimes', 'in:visita_optometrica,prova_lac,consegna_ordine,ritiro_riparazione,generico'],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,confirmed,completed,cancelled,no_show'],
            'start_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'in:15,30,45,60,90'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],
        ]);

        if (isset($data['duration_minutes'])) {
            $appointment->duration_minutes = (int) $data['duration_minutes'];
            $appointment->end_at = $appointment->start_at?->copy()->addMinutes((int) $data['duration_minutes']);
            $appointment->save();
        }

        if (isset($data['start_at'])) {
            $appointment = $service->reschedule($appointment, Carbon::parse((string) $data['start_at']));
        }

        unset($data['start_at'], $data['duration_minutes']);
        if (! empty($data)) {
            $appointment->update($data);
        }

        return new AppointmentResource($appointment->fresh()->load(['patient', 'user', 'order', 'sale']));
    }

    public function destroy(Appointment $appointment, AppointmentService $service): JsonResponse
    {
        $service->cancel($appointment);

        return response()->json(['message' => 'Appuntamento annullato.']);
    }

    public function calendar(Request $request, AppointmentService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'pos_id' => ['required', 'uuid', 'exists:points_of_sale,id'],
        ]);

        $pos = PointOfSale::query()->findOrFail($data['pos_id']);
        $rows = $service->getCalendarData($pos, Carbon::parse((string) $data['from']), Carbon::parse((string) $data['to']));

        return response()->json(['data' => $rows]);
    }

    public function today(Request $request): AnonymousResourceCollection
    {
        $posId = $request->query('pos_id');

        $q = Appointment::query()
            ->with(['patient', 'user'])
            ->today()
            ->orderBy('start_at');

        if ($posId) {
            $q->where('pos_id', $posId);
        }

        return AppointmentResource::collection($q->get());
    }
}

