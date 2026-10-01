import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';

import { AuthService } from '../services/auth.service';
import { API_BASE_URL } from './app-config.tokens';

/** Adds "Authorization: Bearer <jwt>" to requests sent to our API. */
export const authTokenInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(AuthService).token;
  const isApiRequest = req.url.startsWith(inject(API_BASE_URL) + '/');

  return next(token && isApiRequest ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req);
};
