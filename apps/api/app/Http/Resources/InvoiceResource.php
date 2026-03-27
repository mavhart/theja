<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'invoice_number' => $this->invoice_number,
            'formatted_number' => $this->formatted_number,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),

            'pos_id'            => $this->pos_id,
            'organization_id'  => $this->organization_id,
            'sale_id'          => $this->sale_id,
            'patient_id'       => $this->patient_id,

            'type'             => $this->type,
            'status'           => $this->status,

            'customer_fiscal_code' => $this->customer_fiscal_code,
            'customer_vat_number'  => $this->customer_vat_number,
            'customer_name'         => $this->customer_name,
            'customer_address'      => $this->customer_address,
            'customer_city'         => $this->customer_city,
            'customer_cap'          => $this->customer_cap,
            'customer_province'     => $this->customer_province,
            'customer_country'      => $this->customer_country,
            'customer_pec'          => $this->customer_pec,
            'customer_fe_code'      => $this->customer_fe_code,

            'subtotal'   => $this->subtotal,
            'vat_amount' => $this->vat_amount,
            'total'      => $this->total,

            'payment_method' => $this->payment_method,
            'payment_terms'  => $this->payment_terms,

            'sdi_identifier'     => $this->sdi_identifier,
            'sdi_sent_at'        => $this->sdi_sent_at?->toIso8601String(),
            'sdi_response_at'    => $this->sdi_response_at?->toIso8601String(),
            'sdi_response_code'  => $this->sdi_response_code,
            'xml_path'           => $this->xml_path,
            'pdf_path'           => $this->pdf_path,

            'notes' => $this->notes,

            'items' => $this->whenLoaded('items', function () {
                return InvoiceItemResource::collection($this->items);
            }),
        ];
    }
}

