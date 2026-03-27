<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabelTemplateResource;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LabelTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->user()->organization_id;
        $query = LabelTemplate::query()
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)->orWhereNull('organization_id');
            })
            ->orderByDesc('is_default')
            ->orderBy('name');

        return LabelTemplateResource::collection($query->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function store(Request $request): LabelTemplateResource
    {
        $data = $request->validate($this->rules());
        $data['organization_id'] = $request->user()->organization_id;
        $template = LabelTemplate::create($data);

        return new LabelTemplateResource($template);
    }

    public function show(LabelTemplate $labelTemplate): LabelTemplateResource
    {
        return new LabelTemplateResource($labelTemplate);
    }

    public function update(Request $request, LabelTemplate $labelTemplate): LabelTemplateResource
    {
        $data = $request->validate($this->rules(true));
        $labelTemplate->update($data);

        return new LabelTemplateResource($labelTemplate->fresh());
    }

    public function destroy(LabelTemplate $labelTemplate): \Illuminate\Http\JsonResponse
    {
        $labelTemplate->delete();

        return response()->json(['message' => 'Template etichetta eliminato.']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $update = false): array
    {
        $p = $update ? 'sometimes' : 'required';

        return [
            'pos_id'          => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'name'            => [$p, 'string', 'max:255'],
            'paper_format'    => [$p, 'string', 'in:A4,A5'],
            'label_width_mm'  => [$p, 'numeric'],
            'label_height_mm' => [$p, 'numeric'],
            'cols'            => [$p, 'integer', 'min:1'],
            'rows'            => [$p, 'integer', 'min:1'],
            'margin_top_mm'   => ['nullable', 'numeric'],
            'margin_left_mm'  => ['nullable', 'numeric'],
            'spacing_h_mm'    => ['nullable', 'numeric'],
            'spacing_v_mm'    => ['nullable', 'numeric'],
            'fields'          => [$p, 'array'],
            'is_default'      => ['nullable', 'boolean'],
        ];
    }
}
