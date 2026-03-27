<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommunicationTemplateResource;
use App\Models\CommunicationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunicationTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', 'in:email,sms'],
            'trigger' => ['nullable', 'string'],
        ]);

        $orgId = $request->user()->organization_id;
        $q = CommunicationTemplate::query()->where('organization_id', $orgId)->orderBy('trigger');
        if ($request->filled('type')) $q->where('type', $request->string('type'));
        if ($request->filled('trigger')) $q->where('trigger', $request->string('trigger'));

        return CommunicationTemplateResource::collection($q->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function store(Request $request): CommunicationTemplateResource
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'type' => ['required', 'in:email,sms'],
            'trigger' => ['required', 'in:appointment_reminder,order_ready,lac_reminder,prescription_reminder,birthday,custom'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:8'],
        ]);

        $tpl = CommunicationTemplate::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'variables' => $data['variables'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'language' => $data['language'] ?? 'it',
        ]);

        return (new CommunicationTemplateResource($tpl))->response()->setStatusCode(201);
    }

    public function show(CommunicationTemplate $communicationTemplate): CommunicationTemplateResource
    {
        return new CommunicationTemplateResource($communicationTemplate);
    }

    public function update(Request $request, CommunicationTemplate $communicationTemplate): CommunicationTemplateResource
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'type' => ['sometimes', 'in:email,sms'],
            'trigger' => ['sometimes', 'in:appointment_reminder,order_ready,lac_reminder,prescription_reminder,birthday,custom'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:8'],
        ]);

        $communicationTemplate->update($data);
        return new CommunicationTemplateResource($communicationTemplate->fresh());
    }

    public function destroy(CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $communicationTemplate->delete();
        return response()->json(['message' => 'Template eliminato.']);
    }
}

