<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'organization_id'            => $this->organization_id,
            'pos_id'                     => $this->pos_id,
            'title'                      => $this->title,
            'last_name'                  => $this->last_name,
            'first_name'                 => $this->first_name,
            'last_name2'                 => $this->last_name2,
            'gender'                     => $this->gender,
            'address'                    => $this->address,
            'city'                       => $this->city,
            'cap'                        => $this->cap,
            'province'                   => $this->province,
            'country'                    => $this->country,
            'date_of_birth'              => $this->date_of_birth?->format('Y-m-d'),
            'place_of_birth'             => $this->place_of_birth,
            'fiscal_code'                => $this->fiscal_code,
            'vat_number'                 => $this->vat_number,
            'phone'                      => $this->phone,
            'phone2'                     => $this->phone2,
            'mobile'                     => $this->mobile,
            'fax'                        => $this->fax,
            'email'                      => $this->email,
            'email_pec'                  => $this->email_pec,
            'fe_recipient_code'          => $this->fe_recipient_code,
            'billing_address'            => $this->billing_address,
            'billing_city'               => $this->billing_city,
            'billing_cap'                => $this->billing_cap,
            'billing_province'           => $this->billing_province,
            'billing_country'            => $this->billing_country,
            'family_head_id'             => $this->family_head_id,
            'language'                   => $this->language,
            'profession'                 => $this->profession,
            'visual_problem'             => $this->visual_problem,
            'hobby'                      => $this->hobby,
            'referral_source'            => $this->referral_source,
            'referral_note'              => $this->referral_note,
            'referred_by_patient_id'     => $this->referred_by_patient_id,
            'card_member'                => $this->card_member,
            'uses_contact_lenses'        => $this->uses_contact_lenses,
            'gdpr_consent_at'            => $this->gdpr_consent_at?->toIso8601String(),
            'gdpr_marketing_consent'     => $this->gdpr_marketing_consent,
            'gdpr_profiling_consent'     => $this->gdpr_profiling_consent,
            'gdpr_model_printed'         => $this->gdpr_model_printed,
            'communication_sms'          => $this->communication_sms,
            'communication_mail'         => $this->communication_mail,
            'communication_letter'       => $this->communication_letter,
            'notes'                      => $this->notes,
            'private_notes'              => $this->private_notes,
            'inserted_by_user_id'        => $this->inserted_by_user_id,
            'inserted_at_pos_id'         => $this->inserted_at_pos_id,
            'is_active'                  => $this->is_active,
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
