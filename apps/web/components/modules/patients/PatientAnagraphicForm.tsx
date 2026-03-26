'use client';

import { FormEvent, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ApiPatient, ApiPatientPayload } from '@/lib/api';

export interface PatientAnagraphicFormProps {
  initial: ApiPatient | null;
  onSubmit: (payload: ApiPatientPayload) => Promise<void>;
  submitting?: boolean;
  submitLabel?: string;
}

function str(v: string | null | undefined): string {
  return v ?? '';
}

function patientToForm(p: ApiPatient | null): Record<string, string | boolean> {
  if (!p) {
    return {
      title: '',
      last_name: '',
      first_name: '',
      last_name2: '',
      gender: '',
      address: '',
      city: '',
      cap: '',
      province: '',
      country: 'IT',
      date_of_birth: '',
      place_of_birth: '',
      fiscal_code: '',
      vat_number: '',
      phone: '',
      phone2: '',
      mobile: '',
      fax: '',
      email: '',
      email_pec: '',
      fe_recipient_code: '',
      billing_address: '',
      billing_city: '',
      billing_cap: '',
      billing_province: '',
      billing_country: '',
      family_head_id: '',
      language: 'it',
      profession: '',
      visual_problem: '',
      hobby: '',
      referral_source: '',
      referral_note: '',
      referred_by_patient_id: '',
      card_member: false,
      uses_contact_lenses: false,
      gdpr_consent_checked: false,
      gdpr_marketing_consent: false,
      gdpr_profiling_consent: false,
      gdpr_model_printed: '',
      communication_sms: true,
      communication_mail: false,
      communication_letter: false,
      notes: '',
      private_notes: '',
      is_active: true,
    };
  }
  return {
    title: str(p.title),
    last_name: str(p.last_name),
    first_name: str(p.first_name),
    last_name2: str(p.last_name2),
    gender: str(p.gender),
    address: str(p.address),
    city: str(p.city),
    cap: str(p.cap),
    province: str(p.province),
    country: str(p.country) || 'IT',
    date_of_birth: str(p.date_of_birth),
    place_of_birth: str(p.place_of_birth),
    fiscal_code: str(p.fiscal_code),
    vat_number: str(p.vat_number),
    phone: str(p.phone),
    phone2: str(p.phone2),
    mobile: str(p.mobile),
    fax: str(p.fax),
    email: str(p.email),
    email_pec: str(p.email_pec),
    fe_recipient_code: str(p.fe_recipient_code),
    billing_address: str(p.billing_address),
    billing_city: str(p.billing_city),
    billing_cap: str(p.billing_cap),
    billing_province: str(p.billing_province),
    billing_country: str(p.billing_country),
    family_head_id: str(p.family_head_id),
    language: str(p.language) || 'it',
    profession: str(p.profession),
    visual_problem: str(p.visual_problem),
    hobby: str(p.hobby),
    referral_source: str(p.referral_source),
    referral_note: str(p.referral_note),
    referred_by_patient_id: str(p.referred_by_patient_id),
    card_member: p.card_member,
    uses_contact_lenses: p.uses_contact_lenses,
    gdpr_consent_checked: Boolean(p.gdpr_consent_at),
    gdpr_marketing_consent: p.gdpr_marketing_consent,
    gdpr_profiling_consent: p.gdpr_profiling_consent,
    gdpr_model_printed: str(p.gdpr_model_printed),
    communication_sms: p.communication_sms,
    communication_mail: p.communication_mail,
    communication_letter: p.communication_letter,
    notes: str(p.notes),
    private_notes: str(p.private_notes),
    is_active: p.is_active,
  };
}

function formToPayload(f: Record<string, string | boolean>, initial: ApiPatient | null): ApiPatientPayload {
  const emptyToNull = (s: string) => (s.trim() === '' ? null : s.trim());
  const gdprAt =
    f.gdpr_consent_checked === true
      ? (initial?.gdpr_consent_at ?? new Date().toISOString())
      : null;

  return {
    title: emptyToNull(String(f.title)),
    last_name: String(f.last_name).trim(),
    first_name: String(f.first_name).trim(),
    last_name2: emptyToNull(String(f.last_name2)),
    gender: (emptyToNull(String(f.gender)) ?? undefined) as ApiPatientPayload['gender'],
    address: emptyToNull(String(f.address)),
    city: emptyToNull(String(f.city)),
    cap: emptyToNull(String(f.cap)),
    province: emptyToNull(String(f.province)),
    country: emptyToNull(String(f.country)) ?? 'IT',
    date_of_birth: emptyToNull(String(f.date_of_birth)),
    place_of_birth: emptyToNull(String(f.place_of_birth)),
    fiscal_code: emptyToNull(String(f.fiscal_code)),
    vat_number: emptyToNull(String(f.vat_number)),
    phone: emptyToNull(String(f.phone)),
    phone2: emptyToNull(String(f.phone2)),
    mobile: emptyToNull(String(f.mobile)),
    fax: emptyToNull(String(f.fax)),
    email: emptyToNull(String(f.email)),
    email_pec: emptyToNull(String(f.email_pec)),
    fe_recipient_code: emptyToNull(String(f.fe_recipient_code)),
    billing_address: emptyToNull(String(f.billing_address)),
    billing_city: emptyToNull(String(f.billing_city)),
    billing_cap: emptyToNull(String(f.billing_cap)),
    billing_province: emptyToNull(String(f.billing_province)),
    billing_country: emptyToNull(String(f.billing_country)),
    family_head_id: emptyToNull(String(f.family_head_id)),
    language: emptyToNull(String(f.language)) ?? 'it',
    profession: emptyToNull(String(f.profession)),
    visual_problem: emptyToNull(String(f.visual_problem)),
    hobby: emptyToNull(String(f.hobby)),
    referral_source: emptyToNull(String(f.referral_source)),
    referral_note: emptyToNull(String(f.referral_note)),
    referred_by_patient_id: emptyToNull(String(f.referred_by_patient_id)),
    card_member: Boolean(f.card_member),
    uses_contact_lenses: Boolean(f.uses_contact_lenses),
    gdpr_consent_at: gdprAt,
    gdpr_marketing_consent: Boolean(f.gdpr_marketing_consent),
    gdpr_profiling_consent: Boolean(f.gdpr_profiling_consent),
    gdpr_model_printed: emptyToNull(String(f.gdpr_model_printed)),
    communication_sms: Boolean(f.communication_sms),
    communication_mail: Boolean(f.communication_mail),
    communication_letter: Boolean(f.communication_letter),
    notes: emptyToNull(String(f.notes)),
    private_notes: emptyToNull(String(f.private_notes)),
    is_active: Boolean(f.is_active),
  };
}

const inputClass =
  'w-full rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function PatientAnagraphicForm({
  initial,
  onSubmit,
  submitting = false,
  submitLabel = 'Salva',
}: PatientAnagraphicFormProps) {
  const [f, setF] = useState(() => patientToForm(initial));

  useEffect(() => {
    setF(patientToForm(initial));
  }, [initial?.id, initial?.updated_at]);

  const set =
    (key: string) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
      const v = e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value;
      setF((prev) => ({ ...prev, [key]: v }));
    };

  const setBool = (key: string) => (checked: boolean) => {
    setF((prev) => ({ ...prev, [key]: checked }));
  };

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await onSubmit(formToPayload(f, initial));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-8">
      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Dati anagrafici</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Titolo</span>
            <input className={inputClass} value={String(f.title)} onChange={set('title')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Cognome *</span>
            <input required className={inputClass} value={String(f.last_name)} onChange={set('last_name')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Nome *</span>
            <input required className={inputClass} value={String(f.first_name)} onChange={set('first_name')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Secondo cognome</span>
            <input className={inputClass} value={String(f.last_name2)} onChange={set('last_name2')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Genere</span>
            <select className={inputClass} value={String(f.gender)} onChange={set('gender')}>
              <option value="">—</option>
              <option value="M">M</option>
              <option value="F">F</option>
              <option value="altro">Altro</option>
            </select>
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Data di nascita</span>
            <input type="date" className={inputClass} value={String(f.date_of_birth)} onChange={set('date_of_birth')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Luogo di nascita</span>
            <input className={inputClass} value={String(f.place_of_birth)} onChange={set('place_of_birth')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Lingua</span>
            <input className={inputClass} value={String(f.language)} onChange={set('language')} />
          </label>
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Indirizzo</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="sm:col-span-2 flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Indirizzo</span>
            <input className={inputClass} value={String(f.address)} onChange={set('address')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Città</span>
            <input className={inputClass} value={String(f.city)} onChange={set('city')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">CAP</span>
            <input className={inputClass} value={String(f.cap)} onChange={set('cap')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Provincia</span>
            <input className={inputClass} value={String(f.province)} onChange={set('province')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Paese (ISO)</span>
            <input className={inputClass} maxLength={2} value={String(f.country)} onChange={set('country')} />
          </label>
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Contatti e documenti</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Codice fiscale</span>
            <input className={inputClass} value={String(f.fiscal_code)} onChange={set('fiscal_code')} autoComplete="off" />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">P. IVA</span>
            <input className={inputClass} value={String(f.vat_number)} onChange={set('vat_number')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Telefono</span>
            <input className={inputClass} value={String(f.phone)} onChange={set('phone')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Telefono 2</span>
            <input className={inputClass} value={String(f.phone2)} onChange={set('phone2')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Cellulare</span>
            <input className={inputClass} value={String(f.mobile)} onChange={set('mobile')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Fax</span>
            <input className={inputClass} value={String(f.fax)} onChange={set('fax')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Email</span>
            <input type="email" className={inputClass} value={String(f.email)} onChange={set('email')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">PEC</span>
            <input type="email" className={inputClass} value={String(f.email_pec)} onChange={set('email_pec')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Codice destinatario (SDI)</span>
            <input className={inputClass} value={String(f.fe_recipient_code)} onChange={set('fe_recipient_code')} />
          </label>
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Fatturazione (se diversa)</h3>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="sm:col-span-2 flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Indirizzo</span>
            <input className={inputClass} value={String(f.billing_address)} onChange={set('billing_address')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Città</span>
            <input className={inputClass} value={String(f.billing_city)} onChange={set('billing_city')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">CAP</span>
            <input className={inputClass} value={String(f.billing_cap)} onChange={set('billing_cap')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Provincia</span>
            <input className={inputClass} value={String(f.billing_province)} onChange={set('billing_province')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Paese</span>
            <input className={inputClass} maxLength={2} value={String(f.billing_country)} onChange={set('billing_country')} />
          </label>
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Altri dati</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Professione</span>
            <input className={inputClass} value={String(f.profession)} onChange={set('profession')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Problema visivo</span>
            <input className={inputClass} value={String(f.visual_problem)} onChange={set('visual_problem')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Hobby</span>
            <input className={inputClass} value={String(f.hobby)} onChange={set('hobby')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Provenienza</span>
            <input className={inputClass} value={String(f.referral_source)} onChange={set('referral_source')} />
          </label>
          <label className="sm:col-span-2 flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Nota provenienza</span>
            <input className={inputClass} value={String(f.referral_note)} onChange={set('referral_note')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">ID capo famiglia</span>
            <input className={inputClass} value={String(f.family_head_id)} onChange={set('family_head_id')} />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs">Riferito da paziente (ID)</span>
            <input className={inputClass} value={String(f.referred_by_patient_id)} onChange={set('referred_by_patient_id')} />
          </label>
          <label className="flex items-center gap-2 pt-6">
            <input type="checkbox" checked={Boolean(f.card_member)} onChange={(e) => setBool('card_member')(e.target.checked)} />
            <span className="text-sm">Tessera socio</span>
          </label>
          <label className="flex items-center gap-2 pt-6">
            <input
              type="checkbox"
              checked={Boolean(f.uses_contact_lenses)}
              onChange={(e) => setBool('uses_contact_lenses')(e.target.checked)}
            />
            <span className="text-sm">Usa lenti a contatto</span>
          </label>
          <label className="flex items-center gap-2 pt-6">
            <input type="checkbox" checked={Boolean(f.is_active)} onChange={(e) => setBool('is_active')(e.target.checked)} />
            <span className="text-sm">Attivo</span>
          </label>
        </div>
      </section>

      <section className="space-y-3 rounded-xl border border-border bg-muted/10 p-4">
        <h3 className="text-sm font-semibold text-foreground">Privacy / GDPR</h3>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={Boolean(f.gdpr_consent_checked)}
            onChange={(e) => setBool('gdpr_consent_checked')(e.target.checked)}
          />
          <span className="text-sm">Consenso informativa privacy registrato</span>
        </label>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={Boolean(f.gdpr_marketing_consent)}
            onChange={(e) => setBool('gdpr_marketing_consent')(e.target.checked)}
          />
          <span className="text-sm">Consenso marketing</span>
        </label>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={Boolean(f.gdpr_profiling_consent)}
            onChange={(e) => setBool('gdpr_profiling_consent')(e.target.checked)}
          />
          <span className="text-sm">Consenso profilazione</span>
        </label>
        <label className="flex flex-col gap-1 max-w-md">
          <span className="text-muted-foreground text-xs">Modello informativa stampato (riferimento)</span>
          <input className={inputClass} value={String(f.gdpr_model_printed)} onChange={set('gdpr_model_printed')} />
        </label>
      </section>

      <section className="space-y-3 rounded-xl border border-border bg-muted/10 p-4">
        <h3 className="text-sm font-semibold text-foreground">Comunicazioni</h3>
        <div className="flex flex-wrap gap-6">
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={Boolean(f.communication_sms)} onChange={(e) => setBool('communication_sms')(e.target.checked)} />
            <span className="text-sm">SMS</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={Boolean(f.communication_mail)} onChange={(e) => setBool('communication_mail')(e.target.checked)} />
            <span className="text-sm">Posta elettronica</span>
          </label>
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={Boolean(f.communication_letter)}
              onChange={(e) => setBool('communication_letter')(e.target.checked)}
            />
            <span className="text-sm">Lettera cartacea</span>
          </label>
        </div>
      </section>

      <section className="space-y-3">
        <h3 className="text-sm font-semibold text-foreground">Note</h3>
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground text-xs">Note</span>
          <textarea rows={3} className={inputClass} value={String(f.notes)} onChange={set('notes')} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground text-xs">Note private (crittografate)</span>
          <textarea rows={3} className={inputClass} value={String(f.private_notes)} onChange={set('private_notes')} />
        </label>
      </section>

      <div className="flex justify-end">
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Salvataggio…' : submitLabel}
        </Button>
      </div>
    </form>
  );
}
