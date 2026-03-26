<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrescriptionResource;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrescriptionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'patient_id' => ['required', 'uuid'],
        ]);

        $patient = Patient::where('id', $request->patient_id)->firstOrFail();

        $query = Prescription::query()->where('patient_id', $patient->id)->orderByDesc('visit_date');

        return PrescriptionResource::collection($query->paginate(min((int) $request->input('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(array_merge([
            'patient_id'           => ['required', 'uuid'],
            'pos_id'               => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'optician_user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'visit_date'           => ['required', 'date'],
            'is_international'     => ['nullable', 'boolean'],
        ], $this->optometryRules()));

        $patient = Patient::where('id', $data['patient_id'])->firstOrFail();

        $posId = $data['pos_id'] ?? $user->activeSessionForCurrentToken()?->pos_id;
        if (! $posId) {
            return response()->json(['message' => 'POS non attivo o pos_id mancante.'], 422);
        }

        $this->assertPosBelongsToOrg($posId, $user->organization_id);

        unset($data['patient_id']);
        $prescription = Prescription::create(array_merge($data, [
            'patient_id' => $patient->id,
            'pos_id'     => $posId,
        ]));

        return (new PrescriptionResource($prescription))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Prescription $prescription): PrescriptionResource
    {
        return new PrescriptionResource($prescription);
    }

    public function update(Request $request, Prescription $prescription): PrescriptionResource
    {
        $user = $request->user();
        $data = $request->validate(array_merge([
            'pos_id'               => ['sometimes', 'uuid', 'exists:points_of_sale,id'],
            'optician_user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'visit_date'           => ['sometimes', 'date'],
            'is_international'     => ['nullable', 'boolean'],
        ], $this->optometryRules(true)));

        if (isset($data['pos_id'])) {
            $this->assertPosBelongsToOrg($data['pos_id'], $user->organization_id);
        }

        $prescription->update($data);

        return new PrescriptionResource($prescription->fresh());
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function optometryRules(bool $optional = false): array
    {
        $p = $optional ? 'sometimes' : 'nullable';

        $rules = [];
        foreach (['od', 'os'] as $eye) {
            foreach (['far', 'medium', 'near'] as $dist) {
                $rules["{$eye}_sphere_{$dist}"]      = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_cylinder_{$dist}"]    = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_axis_{$dist}"]        = [$p, 'nullable', 'integer'];
                $rules["{$eye}_prism_{$dist}"]       = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_base_{$dist}"]        = [$p, 'nullable', 'string', 'max:64'];
                $rules["{$eye}_addition_{$dist}"]    = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_prism_h_{$dist}"]     = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_base_h_{$dist}"]      = [$p, 'nullable', 'string', 'max:64'];
                $rules["{$eye}_prism_v_{$dist}"]     = [$p, 'nullable', 'numeric'];
                $rules["{$eye}_base_v_{$dist}"]      = [$p, 'nullable', 'string', 'max:64'];
            }
        }

        $rules = array_merge($rules, [
            'visus_od_natural'        => [$p, 'nullable', 'string', 'max:64'],
            'visus_od_corrected'      => [$p, 'nullable', 'string', 'max:64'],
            'visus_os_natural'        => [$p, 'nullable', 'string', 'max:64'],
            'visus_os_corrected'      => [$p, 'nullable', 'string', 'max:64'],
            'visus_bino_natural'      => [$p, 'nullable', 'string', 'max:64'],
            'visus_bino_corrected'    => [$p, 'nullable', 'string', 'max:64'],
            'phoria_far_natural'      => [$p, 'nullable', 'string', 'max:64'],
            'phoria_far_corrected'    => [$p, 'nullable', 'string', 'max:64'],
            'phoria_near_natural'     => [$p, 'nullable', 'string', 'max:64'],
            'phoria_near_corrected'   => [$p, 'nullable', 'string', 'max:64'],
            'dominant_eye_far'        => [$p, 'nullable', 'string', 'max:32'],
            'dominant_eye_near'       => [$p, 'nullable', 'string', 'max:32'],
            'ipd_total'               => [$p, 'nullable', 'numeric'],
            'ipd_right'               => [$p, 'nullable', 'numeric'],
            'ipd_left'                => [$p, 'nullable', 'numeric'],
            'glasses_in_use'          => [$p, 'nullable', 'boolean'],
            'prescribed_by'           => [$p, 'nullable', 'string', 'max:255'],
            'prescribed_at'           => [$p, 'nullable', 'date'],
            'checked_by'              => [$p, 'nullable', 'string', 'max:255'],
            'next_recall_at'          => [$p, 'nullable', 'date'],
            'next_recall_reason'      => [$p, 'nullable', 'string', 'max:255'],
            'next_recall2_at'         => [$p, 'nullable', 'date'],
            'next_recall2_reason'     => [$p, 'nullable', 'string', 'max:255'],
            'notes'                   => [$p, 'nullable', 'string'],
        ]);

        return $rules;
    }

    private function assertPosBelongsToOrg(string $posId, string $organizationId): void
    {
        if (! PointOfSale::where('id', $posId)->where('organization_id', $organizationId)->exists()) {
            abort(422, 'Il POS non appartiene alla tua organizzazione.');
        }
    }
}
