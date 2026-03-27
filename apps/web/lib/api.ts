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

export type ProductCategory =
  | 'montatura'
  | 'lente_oftalmica'
  | 'lente_contatto'
  | 'liquido_accessorio'
  | 'servizio';

export interface ApiSupplier {
  id: string;
  organization_id: string;
  type: 'azienda' | 'persona';
  company_name?: string | null;
  last_name?: string | null;
  first_name?: string | null;
  code?: string | null;
  city?: string | null;
  phone?: string | null;
  categories?: string[];
  is_active: boolean;
  [key: string]: unknown;
}

export interface ApiProduct {
  id: string;
  organization_id: string;
  supplier_id?: string | null;
  category: ProductCategory;
  barcode?: string | null;
  sku?: string | null;
  internal_code?: string | null;
  personal_code?: string | null;
  brand?: string | null;
  line?: string | null;
  model?: string | null;
  color?: string | null;
  material?: string | null;
  lens_type?: string | null;
  lens_color?: string | null;
  user_type?: string | null;
  mounting_type?: string | null;
  caliber?: number | null;
  bridge?: number | null;
  temple?: number | null;
  is_polarized: boolean;
  is_ce: boolean;
  attributes?: Record<string, unknown> | null;
  purchase_price?: string | number | null;
  markup_percent?: string | number | null;
  net_price?: string | number | null;
  list_price?: string | number | null;
  sale_price?: string | number | null;
  vat_code?: string | null;
  vat_rate?: string | number | null;
  inserted_at?: string | null;
  date_start?: string | null;
  date_end?: string | null;
  customs_code?: string | null;
  notes?: string | null;
  is_active: boolean;
  supplier?: ApiSupplier | null;
  created_at?: string;
  updated_at?: string;
}

export type ApiProductPayload = Partial<Omit<ApiProduct, 'id' | 'created_at' | 'updated_at'>>;
export type ApiSupplierPayload = Partial<Omit<ApiSupplier, 'id'>>;

export interface ApiInventoryStockItem {
  id: string;
  pos_id: string;
  product_id: string;
  quantity: number;
  quantity_arriving: number;
  quantity_reserved: number;
  quantity_sold: number;
  min_stock: number;
  max_stock: number;
  location?: string | null;
  product?: ApiProduct;
  pos_name?: string;
}

export interface ApiStockMovement {
  id: string;
  pos_id: string;
  product_id: string;
  user_id?: number | null;
  type: string;
  quantity: number;
  quantity_before: number;
  quantity_after: number;
  ddt_number?: string | null;
  ddt_date?: string | null;
  reference?: string | null;
  purchase_price?: number | null;
  sale_price?: number | null;
  notes?: string | null;
  created_at: string;
}

export interface ApiLacSchedule {
  id: string;
  patient_id: string;
  product_id: string;
  patient_name?: string;
  product_name?: string;
  estimated_end_date: string;
  days_remaining?: number;
  [key: string]: unknown;
}

export interface ApiSaleItem {
  id: string;
  sale_id: string;
  product_id?: string | null;
  description: string;
  quantity: number;
  unit_price: string | number;
  purchase_price?: string | number | null;
  discount_percent: string | number;
  discount_amount: string | number;
  total: string | number;
  vat_rate: string | number;
  vat_code?: string | null;
  sts_code?: string | null;
  lot?: string | null;
  item_type: 'montatura' | 'lente_dx' | 'lente_sx' | 'lente_contatto' | 'accessorio' | 'servizio' | 'altro';
  notes?: string | null;
  product?: ApiProduct | null;
}

export interface ApiPayment {
  id: string;
  sale_id: string;
  amount: string | number;
  method: 'contanti' | 'carta' | 'bonifico' | 'assegno' | 'altro';
  payment_date: string;
  is_scheduled: boolean;
  scheduled_date?: string | null;
  paid_at?: string | null;
  receipt_number?: string | null;
  notes?: string | null;
  created_at: string;
}

export interface ApiSale {
  id: string;
  pos_id: string;
  patient_id?: string | null;
  user_id: number;
  status: 'quote' | 'confirmed' | 'delivered' | 'cancelled';
  type: 'occhiale_vista' | 'occhiale_sole' | 'sostituzione_lenti' | 'sostituzione_montatura' | 'lac' | 'accessorio' | 'servizio' | 'generico';
  sale_date: string;
  delivery_date?: string | null;
  notes?: string | null;
  discount_amount: string | number;
  total_amount: string | number;
  paid_amount: string | number;
  remaining_amount?: string | number;
  is_fully_paid?: boolean;
  status_label?: string;
  prescription_id?: string | null;
  patient?: ApiPatient | null;
  user?: ApiUser | null;
  items?: ApiSaleItem[];
  payments?: ApiPayment[];
  created_at?: string;
  updated_at?: string;
}

export interface ApiOrder {
  id: string;
  pos_id: string;
  sale_id?: string | null;
  patient_id?: string | null;
  user_id: number;
  lab_supplier_id?: string | null;
  status: 'draft' | 'sent' | 'in_progress' | 'ready' | 'delivered' | 'cancelled';
  order_date: string;
  expected_delivery_date?: string | null;
  actual_delivery_date?: string | null;
  job_code?: string | null;
  frame_barcode?: string | null;
  frame_description?: string | null;
  lens_right_product_id?: string | null;
  lens_left_product_id?: string | null;
  lens_right_description?: string | null;
  lens_left_description?: string | null;
  prescription_id?: string | null;
  mounting_type?: string | null;
  notes?: string | null;
  internal_notes?: string | null;
  total_amount: string | number;
  created_at?: string;
  updated_at?: string;
}

export interface ApiAfterSaleEvent {
  id: string;
  sale_id: string;
  sale_item_id?: string | null;
  type: 'riparazione' | 'garanzia' | 'reso' | 'adattamento' | 'altro';
  description: string;
  status: 'aperto' | 'inviato_lab' | 'rientrato' | 'chiuso';
  opened_at: string;
  closed_at?: string | null;
  cost?: string | number | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export type ApiInvoiceStatus = 'draft' | 'issued' | 'sent_sdi' | 'accepted' | 'rejected' | 'cancelled';

export type ApiAppointmentType =
  | 'visita_optometrica'
  | 'prova_lac'
  | 'consegna_ordine'
  | 'ritiro_riparazione'
  | 'generico';

export type ApiAppointmentStatus = 'scheduled' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

export interface ApiAppointment {
  id: string;
  pos_id: string;
  patient_id?: string | null;
  user_id: number;
  type: ApiAppointmentType;
  title?: string | null;
  status: ApiAppointmentStatus;
  start_at: string;
  end_at: string;
  duration_minutes: number;
  duration_label?: string;
  notes?: string | null;
  internal_notes?: string | null;
  reminder_sent_at?: string | null;
  order_id?: string | null;
  sale_id?: string | null;
  patient?: ApiPatient | null;
  user?: ApiUser | null;
}

export type CommunicationTrigger =
  | 'appointment_reminder'
  | 'order_ready'
  | 'lac_reminder'
  | 'prescription_reminder'
  | 'birthday'
  | 'custom';

export interface ApiCommunicationTemplate {
  id: string;
  organization_id: string;
  pos_id?: string | null;
  type: 'email' | 'sms';
  trigger: CommunicationTrigger;
  subject?: string | null;
  body: string;
  variables: string[];
  is_active: boolean;
  language: string;
  created_at?: string;
  updated_at?: string;
}

export interface ApiCommunicationLog {
  id: string;
  organization_id: string;
  pos_id?: string | null;
  patient_id?: string | null;
  type: 'email' | 'sms';
  trigger: string;
  subject?: string | null;
  body: string;
  status: 'pending' | 'sent' | 'failed' | 'bounced';
  sent_at?: string | null;
  error_message?: string | null;
  provider?: string | null;
  provider_message_id?: string | null;
  patient?: ApiPatient | null;
  created_at?: string;
}

export interface ApiInvoiceItem {
  id: string;
  invoice_id: string;
  description: string;
  quantity: number | string;
  unit_price: number | string;
  discount_percent: number | string;
  subtotal: number | string;
  vat_rate: number | string;
  vat_amount: number | string;
  total: number | string;
  sts_code?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ApiInvoice {
  id: string;
  invoice_number: string;
  formatted_number: string;
  invoice_date: string;
  pos_id: string;
  organization_id: string;
  sale_id?: string | null;
  patient_id?: string | null;

  type: 'fattura' | 'ricevuta' | 'fattura_pa';
  status: ApiInvoiceStatus;

  customer_fiscal_code?: string | null;
  customer_vat_number?: string | null;
  customer_name: string;
  customer_address?: string | null;
  customer_city?: string | null;
  customer_cap?: string | null;
  customer_province?: string | null;
  customer_country?: string | null;
  customer_pec?: string | null;
  customer_fe_code?: string | null;

  subtotal: number | string;
  vat_amount: number | string;
  total: number | string;

  payment_method?: string | null;
  payment_terms?: string | null;

  sdi_identifier?: string | null;
  sdi_sent_at?: string | null;
  sdi_response_at?: string | null;
  sdi_response_code?: string | null;
  xml_path?: string | null;
  pdf_path?: string | null;

  notes?: string | null;
  items?: ApiInvoiceItem[];
}

export interface ApiLabelField {
  key: 'barcode' | 'barcode_number' | 'brand' | 'model' | 'caliber_bridge' | 'color' | 'sale_price' | 'supplier';
  label: string;
  visible: boolean;
  font_size: number;
}

export interface ApiLabelTemplate {
  id: string;
  pos_id?: string | null;
  organization_id?: string | null;
  name: string;
  paper_format: 'A4' | 'A5';
  label_width_mm: string | number;
  label_height_mm: string | number;
  cols: number;
  rows: number;
  margin_top_mm: string | number;
  margin_left_mm: string | number;
  spacing_h_mm: string | number;
  spacing_v_mm: string | number;
  fields: ApiLabelField[];
  is_default: boolean;
}

export interface GetProductsParams {
  q?: string;
  category?: ProductCategory | 'all';
  page?: number;
}

export interface GetSuppliersParams {
  q?: string;
  category?: string;
  page?: number;
}

export interface PrintLabelsPayload {
  product_ids: string[];
  template_id: string;
  start_position?: number;
  copies?: number;
}

export interface GetSalesParams {
  status?: string;
  patient_id?: string;
  type?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
}

export interface GetOrdersParams {
  status?: string;
  lab_supplier_id?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
}

export interface GetAppointmentsParams {
  pos_id?: string;
  date_from?: string;
  date_to?: string;
  type?: ApiAppointmentType;
  status?: ApiAppointmentStatus;
  user_id?: string;
  page?: number;
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

export async function getProducts(
  params: GetProductsParams = {},
): Promise<{ data: { data: ApiProduct[]; meta: LaravelPaginationMeta; links: Record<string, string | null> }; status: number }> {
  const q = new URLSearchParams({
    page: String(params.page ?? 1),
    per_page: '20',
  });
  if (params.q?.trim()) q.set('q', params.q.trim());
  if (params.category && params.category !== 'all') q.set('category', params.category);
  return apiRequest<{ data: ApiProduct[]; meta: LaravelPaginationMeta; links: Record<string, string | null> }>(
    `/products?${q.toString()}`,
  );
}

export async function getProduct(
  id: string,
): Promise<{ data: { data: ApiProduct }; status: number }> {
  return apiRequest<{ data: ApiProduct }>(`/products/${id}`);
}

export async function getProductByBarcode(
  barcode: string,
): Promise<{ data: { data?: { source: string; product: ApiProduct } }; status: number }> {
  return apiRequest<{ data?: { source: string; product: ApiProduct } }>(`/products/barcode/${encodeURIComponent(barcode)}`);
}

export async function generateProductBarcode(
  productId: string,
): Promise<{ data: { data: ApiProduct }; status: number }> {
  return apiRequest<{ data: ApiProduct }>(`/products/${productId}/generate-barcode`, { method: 'POST' });
}

export function getProductBarcodeSvgUrl(productId: string): string {
  return `${API_URL}/api/products/${productId}/barcode.svg`;
}

export async function createProduct(
  payload: ApiProductPayload,
): Promise<{ data: { data: ApiProduct }; status: number }> {
  return apiRequest<{ data: ApiProduct }>('/products', {
    method: 'POST',
    body:   JSON.stringify(payload),
  });
}

export async function updateProduct(
  id: string,
  payload: ApiProductPayload,
): Promise<{ data: { data: ApiProduct }; status: number }> {
  return apiRequest<{ data: ApiProduct }>(`/products/${id}`, {
    method: 'PUT',
    body:   JSON.stringify(payload),
  });
}

export async function getSuppliers(
  params: GetSuppliersParams = {},
): Promise<{ data: { data: ApiSupplier[]; meta: LaravelPaginationMeta; links: Record<string, string | null> }; status: number }> {
  const q = new URLSearchParams({
    page: String(params.page ?? 1),
    per_page: '20',
  });
  if (params.q?.trim()) q.set('q', params.q.trim());
  if (params.category?.trim()) q.set('category', params.category.trim());
  return apiRequest<{ data: ApiSupplier[]; meta: LaravelPaginationMeta; links: Record<string, string | null> }>(
    `/suppliers?${q.toString()}`,
  );
}

export async function getSupplier(
  id: string,
): Promise<{ data: { data: ApiSupplier }; status: number }> {
  return apiRequest<{ data: ApiSupplier }>(`/suppliers/${id}`);
}

export async function createSupplier(
  payload: ApiSupplierPayload,
): Promise<{ data: { data: ApiSupplier }; status: number }> {
  return apiRequest<{ data: ApiSupplier }>('/suppliers', {
    method: 'POST',
    body:   JSON.stringify(payload),
  });
}

export async function getInventoryStock(
  productId: string,
): Promise<{ data: { data: ApiInventoryStockItem[] }; status: number }> {
  const q = new URLSearchParams({ product_id: productId });
  return apiRequest<{ data: ApiInventoryStockItem[] }>(`/inventory?${q.toString()}`);
}

export async function createStockMovement(
  payload: Record<string, unknown>,
): Promise<{ data: { data: ApiStockMovement }; status: number }> {
  return apiRequest<{ data: ApiStockMovement }>('/stock-movements', {
    method: 'POST',
    body:   JSON.stringify(payload),
  });
}

export async function getStockMovements(
  productId: string,
): Promise<{ data: { data: ApiStockMovement[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ product_id: productId, per_page: '50' });
  return apiRequest<{ data: ApiStockMovement[]; meta?: LaravelPaginationMeta }>(`/stock-movements?${q.toString()}`);
}

export async function getLacSchedules(
  params: { expiring_days?: number } = {},
): Promise<{ data: { data: ApiLacSchedule[] }; status: number }> {
  const q = new URLSearchParams();
  if (params.expiring_days != null) q.set('expiring_days', String(params.expiring_days));
  return apiRequest<{ data: ApiLacSchedule[] }>(`/lac-schedules${q.toString() ? `?${q.toString()}` : ''}`);
}

export async function getLabelTemplates(
  page = 1,
): Promise<{ data: { data: ApiLabelTemplate[]; meta?: LaravelPaginationMeta }; status: number }> {
  return apiRequest<{ data: ApiLabelTemplate[]; meta?: LaravelPaginationMeta }>(`/label-templates?page=${page}&per_page=100`);
}

export async function createLabelTemplate(
  payload: Partial<ApiLabelTemplate>,
): Promise<{ data: { data: ApiLabelTemplate }; status: number }> {
  return apiRequest<{ data: ApiLabelTemplate }>('/label-templates', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateLabelTemplate(
  id: string,
  payload: Partial<ApiLabelTemplate>,
): Promise<{ data: { data: ApiLabelTemplate }; status: number }> {
  return apiRequest<{ data: ApiLabelTemplate }>(`/label-templates/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function printLabelsPdf(
  payload: PrintLabelsPayload,
): Promise<{ data: { pdf_base64: string; filename: string; label_count: number; pages: number | null }; status: number }> {
  return apiRequest<{ pdf_base64: string; filename: string; label_count: number; pages: number | null }>('/labels/print', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function getSales(
  params: GetSalesParams = {},
): Promise<{ data: { data: ApiSale[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ page: String(params.page ?? 1), per_page: '20' });
  if (params.status) q.set('status', params.status);
  if (params.patient_id) q.set('patient_id', params.patient_id);
  if (params.type) q.set('type', params.type);
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);
  return apiRequest<{ data: ApiSale[]; meta?: LaravelPaginationMeta }>(`/sales?${q.toString()}`);
}

export async function getSale(id: string): Promise<{ data: { data: ApiSale }; status: number }> {
  return apiRequest<{ data: ApiSale }>(`/sales/${id}`);
}

export async function createSale(payload: Record<string, unknown>): Promise<{ data: { data: ApiSale }; status: number }> {
  return apiRequest<{ data: ApiSale }>('/sales', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateSale(id: string, payload: Record<string, unknown>): Promise<{ data: { data: ApiSale }; status: number }> {
  return apiRequest<{ data: ApiSale }>(`/sales/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
}

export async function addSalePayment(id: string, payload: Record<string, unknown>): Promise<{ data: { data: ApiPayment }; status: number }> {
  return apiRequest<{ data: ApiPayment }>(`/sales/${id}/payments`, { method: 'POST', body: JSON.stringify(payload) });
}

export async function scheduleSalePayments(id: string, schedule: Array<Record<string, unknown>>): Promise<{ data: { data: ApiPayment[] }; status: number }> {
  return apiRequest<{ data: ApiPayment[] }>(`/sales/${id}/schedule-payments`, { method: 'POST', body: JSON.stringify({ schedule }) });
}

export async function deliverSale(id: string): Promise<{ data: { data: ApiSale }; status: number }> {
  return apiRequest<{ data: ApiSale }>(`/sales/${id}/deliver`, { method: 'POST' });
}

export async function cancelSale(id: string): Promise<{ data: { data: ApiSale }; status: number }> {
  return apiRequest<{ data: ApiSale }>(`/sales/${id}/cancel`, { method: 'POST' });
}

export async function getSalePaymentSummary(id: string): Promise<{ data: { data: { total_amount: string | number; paid_amount: string | number; remaining_amount: string | number; is_fully_paid: boolean; payments: ApiPayment[] } }; status: number }> {
  return apiRequest<{ data: { total_amount: string | number; paid_amount: string | number; remaining_amount: string | number; is_fully_paid: boolean; payments: ApiPayment[] } }>(`/sales/${id}/payment-summary`);
}

export async function getOrders(
  params: GetOrdersParams = {},
): Promise<{ data: { data: ApiOrder[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ page: String(params.page ?? 1), per_page: '20' });
  if (params.status) q.set('status', params.status);
  if (params.lab_supplier_id) q.set('lab_supplier_id', params.lab_supplier_id);
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);
  return apiRequest<{ data: ApiOrder[]; meta?: LaravelPaginationMeta }>(`/orders?${q.toString()}`);
}

export async function getOrder(id: string): Promise<{ data: { data: ApiOrder }; status: number }> {
  return apiRequest<{ data: ApiOrder }>(`/orders/${id}`);
}

export async function createOrder(payload: Record<string, unknown>): Promise<{ data: { data: ApiOrder }; status: number }> {
  return apiRequest<{ data: ApiOrder }>('/orders', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateOrderStatus(id: string, status: string): Promise<{ data: { data: ApiOrder }; status: number }> {
  return apiRequest<{ data: ApiOrder }>(`/orders/${id}/status`, { method: 'POST', body: JSON.stringify({ status }) });
}

export async function getPendingOrdersDashboard(): Promise<{ data: { data: { sent: number; in_progress: number; ready: number; rows: ApiOrder[] } }; status: number }> {
  return apiRequest<{ data: { sent: number; in_progress: number; ready: number; rows: ApiOrder[] } }>('/orders/pending');
}

export async function getAfterSaleEvents(saleId: string): Promise<{ data: { data: ApiAfterSaleEvent[] }; status: number }> {
  return apiRequest<{ data: ApiAfterSaleEvent[] }>(`/after-sale-events?sale_id=${encodeURIComponent(saleId)}`);
}

export async function createAfterSaleEvent(payload: Record<string, unknown>): Promise<{ data: { data: ApiAfterSaleEvent }; status: number }> {
  return apiRequest<{ data: ApiAfterSaleEvent }>('/after-sale-events', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateAfterSaleEventStatus(id: string, payload: Record<string, unknown>): Promise<{ data: { data: ApiAfterSaleEvent }; status: number }> {
  return apiRequest<{ data: ApiAfterSaleEvent }>(`/after-sale-events/${id}/status`, { method: 'POST', body: JSON.stringify(payload) });
}

export interface GetInvoicesParams {
  status?: ApiInvoiceStatus;
  date_from?: string;
  date_to?: string;
  patient_id?: string;
  page?: number;
}

export async function getInvoices(
  params: GetInvoicesParams = {},
): Promise<{ data: { data: ApiInvoice[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ page: String(params.page ?? 1), per_page: '20' });
  if (params.status) q.set('status', params.status);
  if (params.patient_id) q.set('patient_id', params.patient_id);
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);

  return apiRequest<{ data: ApiInvoice[]; meta?: LaravelPaginationMeta }>(`/invoices?${q.toString()}`);
}

export async function getInvoice(id: string): Promise<{ data: { data: ApiInvoice }; status: number }> {
  return apiRequest<{ data: ApiInvoice }>(`/invoices/${id}`);
}

export async function createInvoice(payload: Record<string, unknown>): Promise<{ data: { data: ApiInvoice }; status: number }> {
  return apiRequest<{ data: ApiInvoice }>('/invoices', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateInvoice(id: string, payload: Record<string, unknown>): Promise<{ data: { data: ApiInvoice }; status: number }> {
  return apiRequest<{ data: ApiInvoice }>(`/invoices/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
}

export async function issueInvoice(id: string): Promise<{ data: { data: ApiInvoice }; status: number }> {
  return apiRequest<{ data: ApiInvoice }>(`/invoices/${id}/issue`, { method: 'POST' });
}

export async function sendSdiInvoice(id: string): Promise<{ data: { data: ApiInvoice }; status: number }> {
  return apiRequest<{ data: ApiInvoice }>(`/invoices/${id}/send-sdi`, { method: 'POST' });
}

export async function fetchInvoicePdf(id: string): Promise<{ data: { filename: string; pdf_base64: string }; status: number }> {
  return apiRequest<{ filename: string; pdf_base64: string }>(`/invoices/${id}/pdf`);
}

export async function fetchInvoiceXml(id: string): Promise<{ data: { filename: string; xml: string }; status: number }> {
  return apiRequest<{ filename: string; xml: string }>(`/invoices/${id}/xml`);
}

export function downloadXml(filename: string, xml: string): void {
  const blob = new Blob([xml], { type: 'application/xml' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export async function getAppointments(
  params: GetAppointmentsParams = {},
): Promise<{ data: { data: ApiAppointment[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ page: String(params.page ?? 1), per_page: '50' });
  if (params.pos_id) q.set('pos_id', params.pos_id);
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);
  if (params.type) q.set('type', params.type);
  if (params.status) q.set('status', params.status);
  if (params.user_id) q.set('user_id', params.user_id);
  return apiRequest<{ data: ApiAppointment[]; meta?: LaravelPaginationMeta }>(`/appointments?${q.toString()}`);
}

export async function getAppointment(id: string): Promise<{ data: { data: ApiAppointment }; status: number }> {
  return apiRequest<{ data: ApiAppointment }>(`/appointments/${id}`);
}

export async function createAppointment(payload: Record<string, unknown>): Promise<{ data: { data: ApiAppointment }; status: number }> {
  return apiRequest<{ data: ApiAppointment }>('/appointments', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateAppointment(id: string, payload: Record<string, unknown>): Promise<{ data: { data: ApiAppointment }; status: number }> {
  return apiRequest<{ data: ApiAppointment }>(`/appointments/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
}

export async function cancelAppointment(id: string): Promise<{ data: { message: string }; status: number }> {
  return apiRequest<{ message: string }>(`/appointments/${id}`, { method: 'DELETE' });
}

export async function getAppointmentsCalendar(params: { from: string; to: string; pos_id: string }): Promise<{ data: { data: ApiAppointment[] }; status: number }> {
  const q = new URLSearchParams({ from: params.from, to: params.to, pos_id: params.pos_id });
  return apiRequest<{ data: ApiAppointment[] }>(`/appointments/calendar?${q.toString()}`);
}

export async function getAppointmentsToday(posId?: string): Promise<{ data: { data: ApiAppointment[] }; status: number }> {
  const q = new URLSearchParams();
  if (posId) q.set('pos_id', posId);
  return apiRequest<{ data: ApiAppointment[] }>(`/appointments/today${q.toString() ? `?${q.toString()}` : ''}`);
}

export async function getCommunicationTemplates(
  page = 1,
): Promise<{ data: { data: ApiCommunicationTemplate[]; meta?: LaravelPaginationMeta }; status: number }> {
  return apiRequest<{ data: ApiCommunicationTemplate[]; meta?: LaravelPaginationMeta }>(`/communication-templates?page=${page}&per_page=100`);
}

export async function createCommunicationTemplate(
  payload: Partial<ApiCommunicationTemplate>,
): Promise<{ data: { data: ApiCommunicationTemplate }; status: number }> {
  return apiRequest<{ data: ApiCommunicationTemplate }>('/communication-templates', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateCommunicationTemplate(
  id: string,
  payload: Partial<ApiCommunicationTemplate>,
): Promise<{ data: { data: ApiCommunicationTemplate }; status: number }> {
  return apiRequest<{ data: ApiCommunicationTemplate }>(`/communication-templates/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function deleteCommunicationTemplate(id: string): Promise<{ data: { message: string }; status: number }> {
  return apiRequest<{ message: string }>(`/communication-templates/${id}`, { method: 'DELETE' });
}

export async function getCommunicationLogs(
  params: { type?: 'email' | 'sms'; status?: 'pending' | 'sent' | 'failed' | 'bounced'; patient_id?: string; date_from?: string; date_to?: string; page?: number } = {},
): Promise<{ data: { data: ApiCommunicationLog[]; meta?: LaravelPaginationMeta }; status: number }> {
  const q = new URLSearchParams({ page: String(params.page ?? 1), per_page: '50' });
  if (params.type) q.set('type', params.type);
  if (params.status) q.set('status', params.status);
  if (params.patient_id) q.set('patient_id', params.patient_id);
  if (params.date_from) q.set('date_from', params.date_from);
  if (params.date_to) q.set('date_to', params.date_to);
  return apiRequest<{ data: ApiCommunicationLog[]; meta?: LaravelPaginationMeta }>(`/communication-logs?${q.toString()}`);
}

export async function getBirthdayPatients(): Promise<{ data: PaginatedPatients; status: number }> {
  return apiRequest<PaginatedPatients>('/patients?birthday_today=1&per_page=50');
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
