import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../services/auth.service';
import { httpErrorMessage } from './http-error';

/**
 * Global HTTP error handling: 401 → logout and redirect to /login, 5xx / network errors → snackbar.
 * 4xx validation errors are left to the calling component.
 */
export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401 && auth.isAuthenticated()) {
          auth.logout();
          void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
          snackBar.open('Сессия истекла, войдите снова', 'OK', { duration: 4000 });
        } else if (error.status === 0 || error.status >= 500) {
          snackBar.open(httpErrorMessage(error, 'Сервер недоступен, попробуйте позже'), 'OK', { duration: 5000 });
        }
      }
      return throwError(() => error);
    }),
  );
};
