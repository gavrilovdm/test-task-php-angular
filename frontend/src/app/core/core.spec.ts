import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';

import { AuthService } from '../services/auth.service';
import { authGuard } from './auth.guard';
import { authInterceptor } from './auth.interceptor';

describe('auth guard & interceptor', () => {
  let auth: jasmine.SpyObj<AuthService>;
  let snackBar: jasmine.SpyObj<MatSnackBar>;

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['isAuthenticated', 'logout'], { token: 'jwt-token' });
    snackBar = jasmine.createSpyObj<MatSnackBar>('MatSnackBar', ['open']);
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
        { provide: MatSnackBar, useValue: snackBar },
      ],
    });
  });

  it('guard redirects anonymous users to /login with returnUrl', () => {
    auth.isAuthenticated.and.returnValue(false);
    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as ActivatedRouteSnapshot, { url: '/products/5' } as RouterStateSnapshot),
    );

    expect(result instanceof UrlTree).toBeTrue();
    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/login?returnUrl=%2Fproducts%2F5');
  });

  it('guard lets authenticated users through', () => {
    auth.isAuthenticated.and.returnValue(true);
    const result = TestBed.runInInjectionContext(() => authGuard({} as ActivatedRouteSnapshot, { url: '/' } as RouterStateSnapshot));
    expect(result).toBeTrue();
  });

  it('interceptor adds the bearer token to API calls', () => {
    TestBed.inject(HttpClient).get('/api/products').subscribe();
    const req = TestBed.inject(HttpTestingController).expectOne('/api/products');
    expect(req.request.headers.get('Authorization')).toBe('Bearer jwt-token');
    req.flush({});
  });

  it('interceptor logs out and redirects on 401', () => {
    const router = TestBed.inject(Router);
    spyOn(router, 'navigate').and.resolveTo(true);

    TestBed.inject(HttpClient).get('/api/products').subscribe({ error: () => undefined });
    TestBed.inject(HttpTestingController).expectOne('/api/products').flush({ error: 'expired' }, { status: 401, statusText: 'Unauthorized' });

    expect(auth.logout).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], jasmine.objectContaining({ queryParams: jasmine.any(Object) }));
  });

  it('interceptor shows a snackbar on 500', () => {
    TestBed.inject(HttpClient).get('/api/products').subscribe({ error: () => undefined });
    TestBed.inject(HttpTestingController).expectOne('/api/products').flush({ error: 'Внутренняя ошибка' }, { status: 500, statusText: 'Error' });

    expect(snackBar.open).toHaveBeenCalledWith('Внутренняя ошибка', 'OK', jasmine.any(Object));
    expect(auth.logout).not.toHaveBeenCalled();
  });
});
