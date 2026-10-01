import { Injectable } from '@angular/core';

import { User } from '../models';

export interface Session {
  token: string;
  expiresAt: string;
  user: User;
}

const STORAGE_KEY = 'products.auth';

/** Persists the auth session in localStorage; falls back to memory-only when storage is unavailable. */
@Injectable({ providedIn: 'root' })
export class SessionStore {
  load(): Session | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? (JSON.parse(raw) as Session) : null;
    } catch {
      return null;
    }
  }

  save(session: Session | null): void {
    try {
      if (session) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // storage unavailable (private mode) — the session lives in memory only
    }
  }
}
