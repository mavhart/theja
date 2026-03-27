<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q'        => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
        ]);

        $query = Product::query()->with('supplier');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('barcode', 'ilike', $term)
                    ->orWhere('sku', 'ilike', $term)
                    ->orWhere('internal_code', 'ilike', $term)
                    ->orWhere('personal_code', 'ilike', $term)
                    ->orWhere('brand', 'ilike', $term)
                    ->orWhere('model', 'ilike', $term)
                    ->orWhereHas('supplier', function ($sq) use ($term) {
                        $sq->where('company_name', 'ilike', $term)
                            ->orWhere('last_name', 'ilike', $term)
                            ->orWhere('first_name', 'ilike', $term);
                    });
            });
        }

        return ProductResource::collection($query->orderBy('brand')->orderBy('model')->paginate(20));
    }

    public function store(Request $request): ProductResource
    {
        $data = $request->validate($this->rules());
        $data['organization_id'] = $request->user()->organization_id;

        $product = Product::create($data);

        return new ProductResource($product->load('supplier'));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('supplier'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $data = $request->validate($this->rules(true));
        $product->update($data);

        return new ProductResource($product->fresh()->load('supplier'));
    }

    public function destroy(Product $product): \Illuminate\Http\JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Prodotto eliminato.']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $update = false): array
    {
        $p = $update ? 'sometimes' : 'nullable';

        return [
            'supplier_id'      => [$p, 'nullable', 'uuid', 'exists:suppliers,id'],
            'category'         => [$update ? 'sometimes' : 'required', 'string', 'in:montatura,lente_oftalmica,lente_contatto,liquido_accessorio,servizio'],
            'barcode'          => [$p, 'nullable', 'string', 'max:128'],
            'sku'              => [$p, 'nullable', 'string', 'max:128'],
            'internal_code'    => [$p, 'nullable', 'string', 'max:128'],
            'personal_code'    => [$p, 'nullable', 'string', 'max:128'],
            'brand'            => [$p, 'nullable', 'string', 'max:255'],
            'line'             => [$p, 'nullable', 'string', 'max:255'],
            'model'            => [$p, 'nullable', 'string', 'max:255'],
            'color'            => [$p, 'nullable', 'string', 'max:255'],
            'material'         => [$p, 'nullable', 'string', 'in:acetato,metallo,titanio,legno,altro'],
            'lens_type'        => [$p, 'nullable', 'string', 'max:64'],
            'lens_color'       => [$p, 'nullable', 'string', 'max:64'],
            'user_type'        => [$p, 'nullable', 'string', 'in:uomo,donna,bambino,unisex'],
            'mounting_type'    => [$p, 'nullable', 'string', 'max:64'],
            'caliber'          => [$p, 'nullable', 'integer'],
            'bridge'           => [$p, 'nullable', 'integer'],
            'temple'           => [$p, 'nullable', 'integer'],
            'is_polarized'     => [$p, 'nullable', 'boolean'],
            'is_ce'            => [$p, 'nullable', 'boolean'],
            'attributes'       => [$p, 'nullable', 'array'],
            'purchase_price'   => [$p, 'nullable', 'numeric'],
            'markup_percent'   => [$p, 'nullable', 'numeric'],
            'net_price'        => [$p, 'nullable', 'numeric'],
            'list_price'       => [$p, 'nullable', 'numeric'],
            'sale_price'       => [$p, 'nullable', 'numeric'],
            'vat_code'         => [$p, 'nullable', 'string', 'max:32'],
            'vat_rate'         => [$p, 'nullable', 'numeric'],
            'inserted_at'      => [$p, 'nullable', 'date'],
            'date_start'       => [$p, 'nullable', 'date'],
            'date_end'         => [$p, 'nullable', 'date'],
            'customs_code'     => [$p, 'nullable', 'string', 'max:64'],
            'notes'            => [$p, 'nullable', 'string'],
            'is_active'        => [$p, 'nullable', 'boolean'],
        ];
    }
}
