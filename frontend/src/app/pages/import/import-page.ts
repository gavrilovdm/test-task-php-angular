import { HttpErrorResponse } from '@angular/common/http';
import { AsyncPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { EMPTY, Observable, Subject, catchError, finalize, switchMap, tap } from 'rxjs';

import { ApiError, ImportJob } from '../../models';
import { ImportService } from '../../services/import.service';
import { ImportStatus } from '../../shared/import-status/import-status';

const MAX_FILE_SIZE = 10 * 1024 * 1024;

@Component({
  selector: 'app-import-page',
  imports: [AsyncPipe, RouterLink, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule, ImportStatus],
  templateUrl: './import-page.html',
  styleUrl: './import-page.scss',
})
export class ImportPage {
  private readonly importService = inject(ImportService);
  private readonly upload$ = new Subject<File>();

  protected readonly file = signal<File | null>(null);
  protected readonly uploading = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Upload → 202 with job id → poll the job status until it is finished. */
  protected readonly job$: Observable<ImportJob> = this.upload$.pipe(
    switchMap((file) =>
      this.importService.upload(file).pipe(
        finalize(() => this.uploading.set(false)),
        tap(() => this.file.set(null)),
        switchMap((job) => this.importService.watch(job.id)),
        catchError((err: unknown) => {
          this.error.set(describeError(err));
          return EMPTY;
        }),
      ),
    ),
  );

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';
    this.error.set(null);
    if (!file) {
      return;
    }
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
      this.error.set('Выберите файл в формате .xlsx');
      return;
    }
    if (file.size > MAX_FILE_SIZE) {
      this.error.set('Размер файла превышает 10 МБ');
      return;
    }
    this.file.set(file);
  }

  upload(): void {
    const file = this.file();
    if (!file) {
      return;
    }
    this.error.set(null);
    this.uploading.set(true);
    this.upload$.next(file);
  }
}

function describeError(err: unknown): string {
  if (err instanceof HttpErrorResponse) {
    const body = err.error as ApiError | null;
    if (body?.errors) {
      return Object.values(body.errors).join('; ');
    }
    if (body?.error) {
      return body.error;
    }
    return `Ошибка загрузки (HTTP ${err.status})`;
  }
  return 'Ошибка загрузки файла';
}
