<?php

namespace App\Http\Controllers;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\PointOfSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Patient::query()->where('is_active', true);

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return PatientResource::collection(
            $query->with('latestPrescription')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($perPage)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->validatePatientData($request);

        $posId = $data['pos_id'] ?? $user->activeSessionForCurrentToken()?->pos_id;
        if (! $posId) {
            return response()->json(['message' => 'POS non attivo: selezionare un POS o inviare pos_id.'], 422);
        }

        $this->assertPosBelongsToOrg($posId, $user->organization_id);

        $patient = Patient::create(array_merge($data, [
            'organization_id'         => $user->organization_id,
            'pos_id'                  => $posId,
            'inserted_by_user_id'     => $user->id,
            'inserted_at_pos_id'      => $posId,
        ]));

        return (new PatientResource($patient->load('latestPrescription')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        return new PatientResource($patient->load('latestPrescription'));
    }

    public function update(Request $request, Patient $patient): PatientResource
    {
        $data = $this->validatePatientData($request, isUpdate: true);
        if (isset($data['pos_id'])) {
            $this->assertPosBelongsToOrg($data['pos_id'], $request->user()->organization_id);
        }
        $patient->update($data);

        return new PatientResource($patient->fresh()->load('latestPrescription'));
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        $patient->delete();

        return response()->json(['message' => 'Paziente eliminato.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePatientData(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'pos_id'                      => [$isUpdate ? 'sometimes' : 'nullable', 'uuid', 'exists:points_of_sale,id'],
            'title'                       => ['nullable', 'string', 'max:32'],
            'last_name'                   => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'first_name'                  => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'last_name2'                  => ['nullable', 'string', 'max:255'],
            'gender'                      => ['nullable', 'string', 'in:M,F,altro'],
            'address'                     => ['nullable', 'string', 'max:255'],
            'city'                        => ['nullable', 'string', 'max:255'],
            'cap'                         => ['nullable', 'string', 'max:16'],
            'province'                    => ['nullable', 'string', 'max:8'],
            'country'                     => ['nullable', 'string', 'size:2'],
            'date_of_birth'               => ['nullable', 'date'],
            'place_of_birth'              => ['nullable', 'string', 'max:255'],
            'fiscal_code'                 => ['nullable', 'string', 'max:32'],
            'vat_number'                  => ['nullable', 'string', 'max:32'],
            'phone'                       => ['nullable', 'string', 'max:64'],
            'phone2'                      => ['nullable', 'string', 'max:64'],
            'mobile'                      => ['nullable', 'string', 'max:64'],
            'fax'                         => ['nullable', 'string', 'max:64'],
            'email'                       => ['nullable', 'email', 'max:255'],
            'email_pec'                   => ['nullable', 'email', 'max:255'],
            'fe_recipient_code'           => ['nullable', 'string', 'max:16'],
            'billing_address'             => ['nullable', 'string'],
            'billing_city'                => ['nullable', 'string', 'max:255'],
            'billing_cap'                 => ['nullable', 'string', 'max:16'],
            'billing_province'            => ['nullable', 'string', 'max:8'],
            'billing_country'             => ['nullable', 'string', 'max:2'],
            'family_head_id'              => ['nullable', 'uuid'],
            'language'                    => ['nullable', 'string', 'max:8'],
            'profession'                  => ['nullable', 'string', 'max:255'],
            'visual_problem'              => ['nullable', 'string', 'max:255'],
            'hobby'                       => ['nullable', 'string', 'max:255'],
            'referral_source'             => ['nullable', 'string', 'max:255'],
            'referral_note'               => ['nullable', 'string', 'max:255'],
            'referred_by_patient_id'      => ['nullable', 'uuid'],
            'card_member'                 => ['nullable', 'boolean'],
            'uses_contact_lenses'         => ['nullable', 'boolean'],
            'gdpr_consent_at'             => ['nullable', 'date'],
            'gdpr_marketing_consent'      => ['nullable', 'boolean'],
            'gdpr_profiling_consent'      => ['nullable', 'boolean'],
            'gdpr_model_printed'          => ['nullable', 'string', 'max:255'],
            'communication_sms'           => ['nullable', 'boolean'],
            'communication_mail'          => ['nullable', 'boolean'],
            'communication_letter'        => ['nullable', 'boolean'],
            'notes'                       => ['nullable', 'string'],
            'private_notes'               => ['nullable', 'string'],
            'is_active'                   => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function assertPosBelongsToOrg(string $posId, string $organizationId): void
    {
        $exists = PointOfSale::where('id', $posId)
            ->where('organization_id', $organizationId)
            ->exists();

        if (! $exists) {
            abort(422, 'Il POS non appartiene alla tua organizzazione.');
        }
    }
}
