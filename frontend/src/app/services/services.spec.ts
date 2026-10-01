import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed, discardPeriodicTasks, fakeAsync, tick } from '@angular/core/testing';

import { ImportJob } from '../models';
import { AuthService } from './auth.service';
import { ImportService } from './import.service';
import { ProductService } from './product.service';

const job = (status: ImportJob['status'], progress: number): ImportJob => ({
  id: 'job-1',
  status,
  fileName: 'f.xlsx',
  progress,
  totalRows: 40,
  processedRows: Math.round((40 * progress) / 100),
  createdCount: 0,
  updatedCount: 0,
  failedCount: 0,
  errors: [],
  createdAt: '2026-01-01T00:00:00Z',
  startedAt: null,
  finishedAt: null,
});

describe('services', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('ProductService builds query params for server-side pagination and filters', () => {
    TestBed.inject(ProductService)
      .getProducts({ page: 3, limit: 50, name: 'боди', priceMin: 100, priceMax: 2000 })
      .subscribe();

    const req = http.expectOne((r) => r.url === '/api/products');
    expect(req.request.params.get('page')).toBe('3');
    expect(req.request.params.get('limit')).toBe('50');
    expect(req.request.params.get('name')).toBe('боди');
    expect(req.request.params.get('price_min')).toBe('100');
    expect(req.request.params.get('price_max')).toBe('2000');
    req.flush({ data: [], meta: { page: 3, limit: 50, total: 0, totalPages: 0 } });
  });

  it('ProductService omits empty filters', () => {
    TestBed.inject(ProductService).getProducts({ page: 1, limit: 20, name: null, priceMin: null }).subscribe();

    const req = http.expectOne((r) => r.url === '/api/products');
    expect(req.request.params.keys()).toEqual(['page', 'limit']);
    req.flush({ data: [], meta: { page: 1, limit: 20, total: 0, totalPages: 0 } });
  });

  it('ImportService uploads the file as multipart form data', () => {
    const file = new File(['x'], 'products.xlsx');
    TestBed.inject(ImportService).upload(file).subscribe();

    const req = http.expectOne('/api/imports');
    expect(req.request.method).toBe('POST');
    expect((req.request.body as FormData).get('file')).toBeTruthy();
    req.flush(job('pending', 0), { status: 202, statusText: 'Accepted' });
  });

  it('ImportService.watch polls until the job is finished', fakeAsync(() => {
    const states: string[] = [];
    let completed = false;
    TestBed.inject(ImportService)
      .watch('job-1', 1000)
      .subscribe({ next: (j) => states.push(`${j.status}:${j.progress}`), complete: () => (completed = true) });

    tick(0);
    http.expectOne('/api/imports/job-1').flush(job('processing', 10));
    tick(1000);
    http.expectOne('/api/imports/job-1').flush(job('processing', 60));
    tick(1000);
    http.expectOne('/api/imports/job-1').flush(job('completed', 100));
    tick(1000);
    http.expectNone('/api/imports/job-1');

    expect(states).toEqual(['processing:10', 'processing:60', 'completed:100']);
    expect(completed).toBeTrue();
    discardPeriodicTasks();
  }));

  it('AuthService stores the session and exposes the token', () => {
    const auth = TestBed.inject(AuthService);
    expect(auth.isAuthenticated()).toBeFalse();

    auth.login('admin@example.com', 'secret').subscribe();
    http.expectOne('/api/auth/login').flush({
      token: 'jwt',
      expiresAt: new Date(Date.now() + 60_000).toISOString(),
      user: { email: 'admin@example.com' },
    });

    expect(auth.isAuthenticated()).toBeTrue();
    expect(auth.token).toBe('jwt');
    expect(auth.user()?.email).toBe('admin@example.com');

    auth.logout();
    expect(auth.isAuthenticated()).toBeFalse();
    expect(localStorage.length).toBe(0);
  });
});
