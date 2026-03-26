/**
 * @theja/shared — Tipi TypeScript condivisi tra apps/api (Laravel) e apps/web (Next.js)
 * Questi tipi vengono popolati progressivamente a partire dalla Fase 1.
 */

// ─── Organization ────────────────────────────────────────────────────────────

export interface Organization {
  id: number;
  name: string;
  slug: string;
  schema_name: string; // tenant_{org_id}
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// ─── Point of Sale ────────────────────────────────────────────────────────────

export interface PointOfSale {
  id: number;
  organization_id: number;
  name: string;
  address?: string;
  city?: string;
  is_active: boolean;
  // Sessioni web
  max_concurrent_web_sessions: number;
  // Feature flags (colonne dirette, non tabella separata)
  feature_pwa: boolean;
  feature_cash_register_virtual: boolean;
  feature_ai_analysis: boolean;
  feature_tessera_sanitaria: boolean;
  // Prezzi add-on (calcolati lato backend)
  created_at: string;
  updated_at: string;
}

// ─── User ─────────────────────────────────────────────────────────────────────

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// ─── Role ─────────────────────────────────────────────────────────────────────

export type SystemRole =
  | 'org_owner'
  | 'pos_manager'
  | 'optician'
  | 'sales'
  | 'cashier';

export interface Role {
  id: number;
  name: string;
  guard_name: string;
  is_system: boolean;    // true = ruolo di sistema non modificabile
  organization_id?: number; // null = ruolo di sistema
  created_at: string;
  updated_at: string;
}

// ─── API Response wrapper ─────────────────────────────────────────────────────

export interface ApiResponse<T> {
  data: T;
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}

// ─── Patient (Fase 2 — allineato a PatientResource / tabella tenant) ─────────

export interface Patient {
  id: string;
  organization_id: string;
  pos_id: string;
  title?: string | null;
  last_name: string;
  first_name: string;
  last_name2?: string | null;
  gender?: 'M' | 'F' | 'altro' | null;
  address?: string | null;
  city?: string | null;
  cap?: string | null;
  province?: string | null;
  country: string;
  date_of_birth?: string | null;
  place_of_birth?: string | null;
  fiscal_code?: string | null;
  vat_number?: string | null;
  phone?: string | null;
  phone2?: string | null;
  mobile?: string | null;
  fax?: string | null;
  email?: string | null;
  email_pec?: string | null;
  fe_recipient_code?: string | null;
  billing_address?: string | null;
  billing_city?: string | null;
  billing_cap?: string | null;
  billing_province?: string | null;
  billing_country?: string | null;
  family_head_id?: string | null;
  language: string;
  profession?: string | null;
  visual_problem?: string | null;
  hobby?: string | null;
  referral_source?: string | null;
  referral_note?: string | null;
  referred_by_patient_id?: string | null;
  card_member: boolean;
  uses_contact_lenses: boolean;
  gdpr_consent_at?: string | null;
  gdpr_marketing_consent: boolean;
  gdpr_profiling_consent: boolean;
  gdpr_model_printed?: string | null;
  communication_sms: boolean;
  communication_mail: boolean;
  communication_letter: boolean;
  notes?: string | null;
  private_notes?: string | null;
  inserted_by_user_id?: number | null;
  inserted_at_pos_id?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/** Valori optometrici per una distanza (Tabo vs internazionale gestito da is_international) */
export type OptometryDistance = 'far' | 'medium' | 'near';

/** Campi sfera/cilindro/asse/… per un occhio e una distanza (nomi colonna API) */
export type EyeOptometryKeys = {
  [K in `${'od' | 'os'}_${'sphere' | 'cylinder' | 'axis' | 'prism' | 'base' | 'addition' | 'prism_h' | 'base_h' | 'prism_v' | 'base_v'}_${OptometryDistance}`]?: string | number | null;
};

export interface Prescription extends EyeOptometryKeys {
  id: string;
  patient_id: string;
  pos_id: string;
  optician_user_id?: number | null;
  visit_date: string;
  is_international: boolean;
  visus_od_natural?: string | null;
  visus_od_corrected?: string | null;
  visus_os_natural?: string | null;
  visus_os_corrected?: string | null;
  visus_bino_natural?: string | null;
  visus_bino_corrected?: string | null;
  phoria_far_natural?: string | null;
  phoria_far_corrected?: string | null;
  phoria_near_natural?: string | null;
  phoria_near_corrected?: string | null;
  dominant_eye_far?: string | null;
  dominant_eye_near?: string | null;
  ipd_total?: string | number | null;
  ipd_right?: string | number | null;
  ipd_left?: string | number | null;
  glasses_in_use: boolean;
  prescribed_by?: string | null;
  prescribed_at?: string | null;
  checked_by?: string | null;
  next_recall_at?: string | null;
  next_recall_reason?: string | null;
  next_recall2_at?: string | null;
  next_recall2_reason?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface LacExam {
  id: string;
  patient_id: string;
  pos_id: string;
  optician_user_id?: number | null;
  exam_date: string;
  od_r1?: number | null;
  od_r2?: number | null;
  od_r1_mm?: number | null;
  od_r2_mm?: number | null;
  od_media?: number | null;
  od_ax_r2?: number | null;
  od_pupil_diameter?: number | null;
  od_corneal_diameter?: number | null;
  od_palpebral_aperture?: number | null;
  od_but_test?: string | null;
  od_schirmer_test?: string | null;
  od_visual_problem?: string | null;
  od_notes?: string | null;
  os_r1?: number | null;
  os_r2?: number | null;
  os_r1_mm?: number | null;
  os_r2_mm?: number | null;
  os_media?: number | null;
  os_ax_r2?: number | null;
  os_pupil_diameter?: number | null;
  os_corneal_diameter?: number | null;
  os_palpebral_aperture?: number | null;
  os_but_test?: string | null;
  os_schirmer_test?: string | null;
  os_visual_problem?: string | null;
  os_notes?: string | null;
  tabs_completed?: Record<string, boolean> | null;
  created_at?: string;
  updated_at?: string;
}
