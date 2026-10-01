import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../services/auth.service';

/** Protects routes: unauthenticated users are redirected to /login?returnUrl=... */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  return auth.isAuthenticated() ? true : inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

/** Keeps authenticated users away from the login page. */
export const guestGuard: CanActivateFn = () => {
  return inject(AuthService).isAuthenticated() ? inject(Router).createUrlTree(['/products']) : true;
};
