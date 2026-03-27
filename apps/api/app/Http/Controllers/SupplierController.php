<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['nullable', 'string'],
            'q'        => ['nullable', 'string', 'max:255'],
        ]);

        $query = Supplier::query()->orderBy('company_name')->orderBy('last_name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'ilike', $term)
                    ->orWhere('last_name', 'ilike', $term)
                    ->orWhere('first_name', 'ilike', $term)
                    ->orWhere('code', 'ilike', $term);
            });
        }

        if ($request->filled('category')) {
            $category = (string) $request->input('category');
            $query->whereJsonContains('categories', $category);
        }

        return SupplierResource::collection($query->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request): SupplierResource
    {
        $data = $request->validate($this->rules());
        $data['organization_id'] = $request->user()->organization_id;

        $supplier = Supplier::create($data);

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(Request $request, Supplier $supplier): SupplierResource
    {
        $data = $request->validate($this->rules(true));
        $supplier->update($data);

        return new SupplierResource($supplier->fresh());
    }

    public function destroy(Supplier $supplier): \Illuminate\Http\JsonResponse
    {
        $supplier->delete();

        return response()->json(['message' => 'Fornitore eliminato.']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $update = false): array
    {
        $p = $update ? 'sometimes' : 'nullable';

        return [
            'type'              => [$update ? 'sometimes' : 'required', 'string', 'in:azienda,persona'],
            'company_name'      => [$p, 'nullable', 'string', 'max:255'],
            'last_name'         => [$p, 'nullable', 'string', 'max:255'],
            'first_name'        => [$p, 'nullable', 'string', 'max:255'],
            'code'              => [$p, 'nullable', 'string', 'max:64'],
            'address'           => [$p, 'nullable', 'string', 'max:255'],
            'city'              => [$p, 'nullable', 'string', 'max:255'],
            'cap'               => [$p, 'nullable', 'string', 'max:16'],
            'province'          => [$p, 'nullable', 'string', 'max:8'],
            'country'           => [$p, 'nullable', 'string', 'size:2'],
            'phone'             => [$p, 'nullable', 'string', 'max:64'],
            'fax'               => [$p, 'nullable', 'string', 'max:64'],
            'toll_free'         => [$p, 'nullable', 'string', 'max:64'],
            'store_code'        => [$p, 'nullable', 'string', 'max:64'],
            'fiscal_code'       => [$p, 'nullable', 'string', 'max:32'],
            'vat_number'        => [$p, 'nullable', 'string', 'max:32'],
            'pec'               => [$p, 'nullable', 'email', 'max:255'],
            'fe_recipient_code' => [$p, 'nullable', 'string', 'max:16'],
            'bank_name'         => [$p, 'nullable', 'string', 'max:255'],
            'abi'               => [$p, 'nullable', 'string', 'max:16'],
            'cab'               => [$p, 'nullable', 'string', 'max:16'],
            'bic_swift'         => [$p, 'nullable', 'string', 'max:16'],
            'iban'              => [$p, 'nullable', 'string', 'max:34'],
            'account_number'    => [$p, 'nullable', 'string', 'max:64'],
            'payment_method'    => [$p, 'nullable', 'string', 'max:255'],
            'email'             => [$p, 'nullable', 'email', 'max:255'],
            'website'           => [$p, 'nullable', 'string', 'max:255'],
            'accountant_code'   => [$p, 'nullable', 'string', 'max:64'],
            'user_id_catalog'   => [$p, 'nullable', 'string', 'max:128'],
            'password_catalog'  => [$p, 'nullable', 'string', 'max:255'],
            'user_id_images'    => [$p, 'nullable', 'string', 'max:128'],
            'password_images'   => [$p, 'nullable', 'string', 'max:255'],
            'notes'             => [$p, 'nullable', 'string'],
            'categories'        => [$p, 'nullable', 'array'],
            'is_active'         => [$p, 'nullable', 'boolean'],
        ];
    }
}
