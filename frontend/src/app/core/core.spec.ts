import { HttpClient, HttpErrorResponse, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';

import { AuthService } from '../services/auth.service';
import { authGuard } from './auth.guard';
import { authTokenInterceptor } from './auth-token.interceptor';
import { httpErrorMessage } from './http-error';
import { httpErrorInterceptor } from './http-error.interceptor';

describe('core', () => {
  let auth: jasmine.SpyObj<AuthService>;
  let snackBar: jasmine.SpyObj<MatSnackBar>;
  let http: HttpClient;
  let backend: HttpTestingController;

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', ['isAuthenticated', 'logout'], { token: 'jwt-token' });
    snackBar = jasmine.createSpyObj<MatSnackBar>('MatSnackBar', ['open']);
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([authTokenInterceptor, httpErrorInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
        { provide: MatSnackBar, useValue: snackBar },
      ],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  describe('authGuard', () => {
    it('redirects anonymous users to /login with returnUrl', () => {
      auth.isAuthenticated.and.returnValue(false);
      const result = TestBed.runInInjectionContext(() =>
        authGuard({} as ActivatedRouteSnapshot, { url: '/products/5' } as RouterStateSnapshot),
      );

      expect(result instanceof UrlTree).toBeTrue();
      expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/login?returnUrl=%2Fproducts%2F5');
    });

    it('lets authenticated users through', () => {
      auth.isAuthenticated.and.returnValue(true);
      const result = TestBed.runInInjectionContext(() => authGuard({} as ActivatedRouteSnapshot, { url: '/' } as RouterStateSnapshot));
      expect(result).toBeTrue();
    });
  });

  describe('authTokenInterceptor', () => {
    it('adds the bearer token to API calls only', () => {
      http.get('/api/products').subscribe();
      http.get('https://cdn.example.com/x.json').subscribe();

      expect(backend.expectOne('/api/products').request.headers.get('Authorization')).toBe('Bearer jwt-token');
      expect(backend.expectOne('https://cdn.example.com/x.json').request.headers.has('Authorization')).toBeFalse();
    });
  });

  describe('httpErrorInterceptor', () => {
    it('logs out and redirects on 401 of an authenticated user', () => {
      auth.isAuthenticated.and.returnValue(true);
      const router = TestBed.inject(Router);
      spyOn(router, 'navigate').and.resolveTo(true);

      http.get('/api/products').subscribe({ error: () => undefined });
      backend.expectOne('/api/products').flush({ error: 'expired' }, { status: 401, statusText: 'Unauthorized' });

      expect(auth.logout).toHaveBeenCalled();
      expect(router.navigate).toHaveBeenCalledWith(['/login'], jasmine.objectContaining({ queryParams: jasmine.any(Object) }));
    });

    it('does not redirect on 401 of the login request itself', () => {
      auth.isAuthenticated.and.returnValue(false);

      http.post('/api/auth/login', {}).subscribe({ error: () => undefined });
      backend.expectOne('/api/auth/login').flush({ error: 'bad' }, { status: 401, statusText: 'Unauthorized' });

      expect(auth.logout).not.toHaveBeenCalled();
    });

    it('shows a snackbar on 500', () => {
      http.get('/api/products').subscribe({ error: () => undefined });
      backend.expectOne('/api/products').flush({ error: 'Внутренняя ошибка' }, { status: 500, statusText: 'Error' });

      expect(snackBar.open).toHaveBeenCalledWith('Внутренняя ошибка', 'OK', jasmine.any(Object));
    });
  });

  describe('httpErrorMessage', () => {
    it('prefers field errors, then the error text, then the fallback', () => {
      const withFields = new HttpErrorResponse({ status: 422, error: { error: 'Invalid', errors: { a: 'A', b: 'B' } } });
      const withText = new HttpErrorResponse({ status: 429, error: { error: 'Too many' } });
      const empty = new HttpErrorResponse({ status: 502, error: null });

      expect(httpErrorMessage(withFields, 'x')).toBe('A; B');
      expect(httpErrorMessage(withText, 'x')).toBe('Too many');
      expect(httpErrorMessage(empty, 'Ошибка')).toBe('Ошибка (HTTP 502)');
      expect(httpErrorMessage(new Error('boom'), 'Ошибка')).toBe('Ошибка');
    });
  });
});
