import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { API_BASE_URL } from '../core/app-config.tokens';
import { LoginResponse } from '../models';
import { Session, SessionStore } from './session.store';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = inject(API_BASE_URL);
  private readonly store = inject(SessionStore);
  private readonly session = signal<Session | null>(this.store.load());

  readonly user = computed(() => this.session()?.user ?? null);

  get token(): string | null {
    return this.isAuthenticated() ? (this.session()?.token ?? null) : null;
  }

  isAuthenticated(): boolean {
    const session = this.session();
    return session !== null && new Date(session.expiresAt).getTime() > Date.now();
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${this.baseUrl}/auth/login`, { email, password })
      .pipe(tap(({ token, expiresAt, user }) => this.setSession({ token, expiresAt, user })));
  }

  logout(): void {
    this.setSession(null);
  }

  private setSession(session: Session | null): void {
    this.session.set(session);
    this.store.save(session);
  }
}
