<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'pos_id', 'title', 'last_name', 'first_name', 'last_name2',
        'gender', 'address', 'city', 'cap', 'province', 'country', 'date_of_birth', 'place_of_birth',
        'fiscal_code', 'vat_number', 'phone', 'phone2', 'mobile', 'fax', 'email', 'email_pec',
        'fe_recipient_code', 'billing_address', 'billing_city', 'billing_cap', 'billing_province',
        'billing_country', 'family_head_id', 'language', 'profession', 'visual_problem', 'hobby',
        'referral_source', 'referral_note', 'referred_by_patient_id', 'card_member', 'uses_contact_lenses',
        'gdpr_consent_at', 'gdpr_marketing_consent', 'gdpr_profiling_consent', 'gdpr_model_printed',
        'communication_sms', 'communication_mail', 'communication_letter', 'notes', 'private_notes',
        'inserted_by_user_id', 'inserted_at_pos_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'           => 'date',
            'gdpr_consent_at'         => 'datetime',
            'card_member'             => 'boolean',
            'uses_contact_lenses'     => 'boolean',
            'gdpr_marketing_consent'  => 'boolean',
            'gdpr_profiling_consent'  => 'boolean',
            'communication_sms'       => 'boolean',
            'communication_mail'      => 'boolean',
            'communication_letter'    => 'boolean',
            'is_active'               => 'boolean',
            'fiscal_code'             => 'encrypted',
            'private_notes'           => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function familyHead(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'family_head_id');
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(Patient::class, 'family_head_id');
    }

    public function referredByPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referred_by_patient_id');
    }

    public function insertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by_user_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function lacExams(): HasMany
    {
        return $this->hasMany(LacExam::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('last_name', 'ilike', '%' . $term . '%')
                ->orWhere('first_name', 'ilike', '%' . $term . '%')
                ->orWhere('mobile', 'ilike', '%' . $term . '%')
                ->orWhere('phone', 'ilike', '%' . $term . '%')
                ->orWhere('phone2', 'ilike', '%' . $term . '%');

            if (strlen($term) >= 11) {
                $q->orWhere('fiscal_code', $term);
            }
        });
    }
}
