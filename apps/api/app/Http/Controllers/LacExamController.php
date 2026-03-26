<?php

namespace App\Http\Controllers;

use App\Http\Resources\LacExamResource;
use App\Models\LacExam;
use App\Models\Patient;
use App\Models\PointOfSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LacExamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'patient_id' => ['required', 'uuid'],
        ]);

        $patient = Patient::where('id', $request->patient_id)->firstOrFail();

        $query = LacExam::query()->where('patient_id', $patient->id)->orderByDesc('exam_date');

        return LacExamResource::collection($query->paginate(min((int) $request->input('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(array_merge([
            'patient_id'       => ['required', 'uuid'],
            'pos_id'           => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'optician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'exam_date'        => ['required', 'date'],
            'tabs_completed'   => ['nullable', 'array'],
        ], $this->lacEyeRules()));

        $patient = Patient::where('id', $data['patient_id'])->firstOrFail();

        $posId = $data['pos_id'] ?? $user->activeSessionForCurrentToken()?->pos_id;
        if (! $posId) {
            return response()->json(['message' => 'POS non attivo o pos_id mancante.'], 422);
        }

        $this->assertPosBelongsToOrg($posId, $user->organization_id);

        unset($data['patient_id']);
        $exam = LacExam::create(array_merge($data, [
            'patient_id' => $patient->id,
            'pos_id'     => $posId,
        ]));

        return (new LacExamResource($exam))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, LacExam $lacExam): LacExamResource
    {
        return new LacExamResource($lacExam);
    }

    public function update(Request $request, LacExam $lacExam): LacExamResource
    {
        $user = $request->user();
        $data = $request->validate(array_merge([
            'pos_id'           => ['sometimes', 'uuid', 'exists:points_of_sale,id'],
            'optician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'exam_date'        => ['sometimes', 'date'],
            'tabs_completed'   => ['nullable', 'array'],
        ], $this->lacEyeRules(true)));

        if (isset($data['pos_id'])) {
            $this->assertPosBelongsToOrg($data['pos_id'], $user->organization_id);
        }

        $lacExam->update($data);

        return new LacExamResource($lacExam->fresh());
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function lacEyeRules(bool $optional = false): array
    {
        $p = $optional ? 'sometimes' : 'nullable';

        $rules = [];
        foreach (['od', 'os'] as $eye) {
            $rules["{$eye}_r1"]                = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_r2"]                = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_r1_mm"]             = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_r2_mm"]             = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_media"]             = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_ax_r2"]             = [$p, 'nullable', 'integer'];
            $rules["{$eye}_pupil_diameter"]    = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_corneal_diameter"]  = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_palpebral_aperture"] = [$p, 'nullable', 'numeric'];
            $rules["{$eye}_but_test"]          = [$p, 'nullable', 'string', 'max:255'];
            $rules["{$eye}_schirmer_test"]      = [$p, 'nullable', 'string', 'max:255'];
            $rules["{$eye}_visual_problem"]      = [$p, 'nullable', 'string', 'max:255'];
            $rules["{$eye}_notes"]              = [$p, 'nullable', 'string'];
        }

        return $rules;
    }

    private function assertPosBelongsToOrg(string $posId, string $organizationId): void
    {
        if (! PointOfSale::where('id', $posId)->where('organization_id', $organizationId)->exists()) {
            abort(422, 'Il POS non appartiene alla tua organizzazione.');
        }
    }
}
