import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, switchMap, takeWhile, timer } from 'rxjs';

import { ImportJob, isImportFinished } from '../models';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = '/api/imports';

  /** Uploads an .xlsx file; the backend queues the job and answers 202 immediately. */
  upload(file: File): Observable<ImportJob> {
    const body = new FormData();
    body.append('file', file, file.name);
    return this.http.post<ImportJob>(this.baseUrl, body);
  }

  getJob(id: string): Observable<ImportJob> {
    return this.http.get<ImportJob>(`${this.baseUrl}/${id}`);
  }

  getLatest(limit = 1): Observable<ImportJob[]> {
    return this.http
      .get<{ data: ImportJob[] }>(this.baseUrl, { params: { limit } })
      .pipe(map((response) => response.data));
  }

  /** Polls the job status until it is finished (the last, finished state is emitted too). */
  watch(id: string, intervalMs = 1000): Observable<ImportJob> {
    return timer(0, intervalMs).pipe(
      switchMap(() => this.getJob(id)),
      takeWhile((job) => !isImportFinished(job), true),
    );
  }
}
