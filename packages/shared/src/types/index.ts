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
