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

// ─── Helpers ─────────────────────────────────────────────────────────────────

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(STORAGE_TOKEN);
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
