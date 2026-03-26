/**
 * Client API tipizzato per comunicare con apps/api (Laravel).
 * Gestisce il token Sanctum da localStorage e gli header comuni.
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

// ─── Chiavi localStorage ──────────────────────────────────────────────────────
export const STORAGE_TOKEN      = 'theja_token';
export const STORAGE_SESSION_ID = 'theja_session_id';
export const STORAGE_POS        = 'theja_active_pos';
export const STORAGE_USER       = 'theja_user';

// ─── Tipi risposta API ────────────────────────────────────────────────────────

export interface ApiPointOfSale {
  id:   string;
  name: string;
  city: string;
}

export interface ApiUser {
  id:              number;
  name:            string;
  email:           string;
  organization_id: string;
  is_active:       boolean;
}

export interface ActiveSession {
  id:             string;
  device_name:    string;
  last_active_at: string;
  platform:       'web' | 'pwa';
}

export interface LoginResponse {
  token?:                  string;
  user?:                   ApiUser;
  points_of_sale?:         ApiPointOfSale[];
  requires_pos_selection?: boolean;
  active_pos?:             ApiPointOfSale;
  permissions?:            string[];
  session_id?:             string;
  message?:                string; // presente nelle risposte di errore (401, 422, ecc.)
}

export interface SelectPosResponse {
  active_pos:  ApiPointOfSale;
  permissions: string[];
  session_id:  string;
}

export interface SessionLimitError {
  error:           'session_limit_reached';
  active_sessions: ActiveSession[];
}

/** Paziente — allineato a PatientResource Laravel */
export interface ApiPatient {
  id: string;
  organization_id: string;
  pos_id: string;
  title?: string | null;
  last_name: string;
  first_name: string;
  last_name2?: string | null;
  gender?: string | null;
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
  /** Calcolato lato API dall'ultima visita / richiamo */
  prescription_alert?: 'none' | 'warning' | 'expired';
  last_prescription_visit_date?: string | null;
}

export type ApiPatientPayload = Partial<Omit<ApiPatient, 'id' | 'created_at' | 'updated_at'>> & {
  last_name?: string;
  first_name?: string;
};

export interface LaravelPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number;
  to?: number;
}

export interface PaginatedPatients {
  data: ApiPatient[];
  meta: LaravelPaginationMeta;
  links: Record<string, string | null>;
}

export interface SinglePatientResponse {
  data: ApiPatient;
}

/** Prescrizione optometrica (risposta API) */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ApiPrescription = Record<string, any>;

export interface PaginatedPrescriptions {
  data: ApiPrescription[];
  meta: LaravelPaginationMeta;
  links: Record<string, string | null>;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ApiLacExam = Record<string, any>;

export interface PaginatedLacExams {
  data: ApiLacExam[];
  meta: LaravelPaginationMeta;
  links: Record<string, string | null>;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(STORAGE_TOKEN);
}

/** ID del POS attivo (da localStorage dopo select-pos). */
export function getStoredPosId(): string | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(STORAGE_POS);
    if (!raw) return null;
    const pos = JSON.parse(raw) as ApiPointOfSale;
    return pos.id ?? null;
  } catch {
    return null;
  }
}

export interface ApiPosUser {
  id:    number;
  name:  string;
  email: string;
}

export async function getPosUsers(
  posId?: string,
): Promise<{ data: { data: ApiPosUser[] }; status: number }> {
  const q = posId ? `?pos_id=${encodeURIComponent(posId)}` : '';
  return apiRequest<{ data: ApiPosUser[] }>(`/users${q}`);
}

export function clearAuthStorage(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(STORAGE_TOKEN);
  localStorage.removeItem(STORAGE_SESSION_ID);
  localStorage.removeItem(STORAGE_POS);
  localStorage.removeItem(STORAGE_USER);
}

// ─── API calls ────────────────────────────────────────────────────────────────

async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<{ data: T; status: number }> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept:         'application/json',
    ...(options.headers as Record<string, string>),
  };

  const stored = token ?? getStoredToken();
  if (stored) {
    headers['Authorization'] = `Bearer ${stored}`;
  }

  const res = await fetch(`${API_URL}/api${path}`, {
    ...options,
    headers,
  });

  const data = await res.json().catch(() => ({}));

  return { data: data as T, status: res.status };
}

export async function getPatients(
  search: string,
  page: number,
): Promise<{ data: PaginatedPatients; status: number }> {
  const q = new URLSearchParams({ page: String(page), per_page: '20' });
  if (search.trim()) q.set('q', search.trim());
  return apiRequest<PaginatedPatients>(`/patients?${q.toString()}`);
}

export async function getPatient(id: string): Promise<{ data: SinglePatientResponse; status: number }> {
  return apiRequest<SinglePatientResponse>(`/patients/${id}`);
}

export async function createPatient(
  payload: ApiPatientPayload,
): Promise<{ data: SinglePatientResponse; status: number }> {
  return apiRequest<SinglePatientResponse>('/patients', {
    method: 'POST',
    body:   JSON.stringify(payload),
  });
}

export async function updatePatient(
  id: string,
  payload: ApiPatientPayload,
): Promise<{ data: SinglePatientResponse; status: number }> {
  return apiRequest<SinglePatientResponse>(`/patients/${id}`, {
    method: 'PUT',
    body:   JSON.stringify(payload),
  });
}

export async function getPrescriptions(
  patientId: string,
  page = 1,
): Promise<{ data: PaginatedPrescriptions; status: number }> {
  const q = new URLSearchParams({ patient_id: patientId, per_page: '50', page: String(page) });
  return apiRequest<PaginatedPrescriptions>(`/prescriptions?${q.toString()}`);
}

export async function createPrescription(
  patientId: string,
  payload: Record<string, unknown>,
): Promise<{ data: { data: ApiPrescription }; status: number }> {
  return apiRequest<{ data: ApiPrescription }>('/prescriptions', {
    method: 'POST',
    body:   JSON.stringify({ patient_id: patientId, ...payload }),
  });
}

export async function updatePrescription(
  id: string,
  payload: Record<string, unknown>,
): Promise<{ data: { data: ApiPrescription }; status: number }> {
  return apiRequest<{ data: ApiPrescription }>(`/prescriptions/${id}`, {
    method: 'PUT',
    body:   JSON.stringify(payload),
  });
}

export async function getLacExams(
  patientId: string,
  page = 1,
): Promise<{ data: PaginatedLacExams; status: number }> {
  const q = new URLSearchParams({ patient_id: patientId, per_page: '50', page: String(page) });
  return apiRequest<PaginatedLacExams>(`/lac-exams?${q.toString()}`);
}

/** Valori estratti da OCR (campi lontano + confidence). */
export interface OcrPrescriptionResult {
  od_sphere_far: number | null;
  os_sphere_far: number | null;
  od_cylinder_far: number | null;
  os_cylinder_far: number | null;
  od_axis_far: number | null;
  os_axis_far: number | null;
  od_addition_far: number | null;
  os_addition_far: number | null;
  confidence: 'high' | 'medium' | 'low';
}

export async function createLacExam(
  patientId: string,
  payload: Record<string, unknown>,
): Promise<{ data: { data: ApiLacExam }; status: number }> {
  return apiRequest<{ data: ApiLacExam }>('/lac-exams', {
    method: 'POST',
    body:   JSON.stringify({ patient_id: patientId, ...payload }),
  });
}

export async function updateLacExam(
  id: string,
  payload: Record<string, unknown>,
): Promise<{ data: { data: ApiLacExam }; status: number }> {
  return apiRequest<{ data: ApiLacExam }>(`/lac-exams/${id}`, {
    method: 'PUT',
    body:   JSON.stringify(payload),
  });
}

export interface PdfDownloadResponse {
  filename:   string;
  pdf_base64: string;
}

export async function scanPrescriptionOcr(
  patientId: string,
  imageBase64: string,
): Promise<{ data: { data: OcrPrescriptionResult }; status: number }> {
  return apiRequest<{ data: OcrPrescriptionResult }>(`/patients/${patientId}/prescriptions/ocr`, {
    method: 'POST',
    body:   JSON.stringify({ image_base64: imageBase64 }),
  });
}

export async function fetchPrescriptionPdf(
  patientId: string,
  prescriptionId: string,
  type: 'referto' | 'certificato',
): Promise<{ data: PdfDownloadResponse; status: number }> {
  const q = new URLSearchParams({ type });
  return apiRequest<PdfDownloadResponse>(
    `/patients/${patientId}/prescriptions/${prescriptionId}/pdf?${q.toString()}`,
  );
}

export async function fetchLacExamPdf(
  patientId: string,
  examId: string,
): Promise<{ data: PdfDownloadResponse; status: number }> {
  return apiRequest<PdfDownloadResponse>(`/patients/${patientId}/lac-exams/${examId}/pdf`);
}

/** Scarica un PDF restituito dall&apos;API (campo pdf_base64). */
export function downloadPdfFromBase64(filename: string, pdfBase64: string): void {
  const bin = atob(pdfBase64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  const blob = new Blob([bytes], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export const api = {
  login: (email: string, password: string) =>
    apiRequest<LoginResponse>('/auth/login', {
      method: 'POST',
      body:   JSON.stringify({ email, password }),
    }),

  selectPos: (posId: string, token: string, deviceName?: string) =>
    apiRequest<SelectPosResponse>(
      '/auth/select-pos',
      {
        method:  'POST',
        body:    JSON.stringify({ pos_id: posId }),
        headers: {
          'X-Device-Name': deviceName ?? (typeof navigator !== 'undefined' ? navigator.userAgent : 'Browser'),
          'X-Platform':    'web',
        },
      },
      token,
    ),

  logout: (token: string) =>
    apiRequest<{ message: string }>('/auth/logout', { method: 'POST' }, token),

  me: () =>
    apiRequest<{ user: ApiUser; active_pos: ApiPointOfSale; permissions: string[]; session_id: string }>('/auth/me'),

  deleteSession: (sessionId: string) =>
    apiRequest<{ message: string }>(`/sessions/${sessionId}`, { method: 'DELETE' }),

  listSessions: () =>
    apiRequest<{ data: ActiveSession[] }>('/sessions'),
};
