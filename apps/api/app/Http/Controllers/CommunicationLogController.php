<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommunicationLogResource;
use App\Models\CommunicationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunicationLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', 'in:email,sms'],
            'status' => ['nullable', 'in:pending,sent,failed,bounced'],
            'patient_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $orgId = $request->user()->organization_id;
        $q = CommunicationLog::query()->where('organization_id', $orgId)->with('patient')->orderByDesc('created_at');
        if ($request->filled('type')) $q->where('type', $request->string('type'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('patient_id')) $q->where('patient_id', $request->string('patient_id'));
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->string('date_from'));
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->string('date_to'));

        return CommunicationLogResource::collection($q->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function show(CommunicationLog $communicationLog): CommunicationLogResource
    {
        return new CommunicationLogResource($communicationLog->load('patient'));
    }
}

