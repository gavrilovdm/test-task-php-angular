import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../services/auth.service';

/**
 * Adds the JWT to API requests and handles global errors:
 * 401 → logout + redirect to /login, 5xx / network errors → snackbar.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  const token = auth.token;
  const request = token && req.url.startsWith('/api/') ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401 && !req.url.endsWith('/auth/login')) {
          auth.logout();
          void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
          snackBar.open('Сессия истекла, войдите снова', 'OK', { duration: 4000 });
        } else if (error.status === 0 || error.status >= 500) {
          const message = (error.error as { error?: string } | null)?.error ?? 'Сервер недоступен, попробуйте позже';
          snackBar.open(message, 'OK', { duration: 5000 });
        }
      }
      return throwError(() => error);
    }),
  );
};
