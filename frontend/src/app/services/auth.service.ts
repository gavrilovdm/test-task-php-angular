import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { LoginResponse, User } from '../models';

const STORAGE_KEY = 'products.auth';

interface StoredSession {
  token: string;
  expiresAt: string;
  user: User;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly session = signal<StoredSession | null>(this.restore());

  readonly user = computed(() => this.session()?.user ?? null);

  get token(): string | null {
    return this.isAuthenticated() ? (this.session()?.token ?? null) : null;
  }

  isAuthenticated(): boolean {
    const session = this.session();
    return session !== null && new Date(session.expiresAt).getTime() > Date.now();
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>('/api/auth/login', { email, password }).pipe(
      tap((response) => {
        const session: StoredSession = { token: response.token, expiresAt: response.expiresAt, user: response.user };
        this.session.set(session);
        this.persist(session);
      }),
    );
  }

  logout(): void {
    this.session.set(null);
    this.persist(null);
  }

  private restore(): StoredSession | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? (JSON.parse(raw) as StoredSession) : null;
    } catch {
      return null;
    }
  }

  private persist(session: StoredSession | null): void {
    try {
      if (session) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // storage unavailable (private mode) — session lives in memory only
    }
  }
}
