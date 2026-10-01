import { AsyncPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { EMPTY, Observable, Subject, catchError, finalize, switchMap, tap } from 'rxjs';

import { IMPORT_MAX_FILE_SIZE } from '../../core/app-config.tokens';
import { httpErrorMessage } from '../../core/http-error';
import { ImportJob } from '../../models';
import { ImportService } from '../../services/import.service';
import { ImportStatus } from '../../shared/import-status/import-status';

@Component({
  selector: 'app-import-page',
  imports: [AsyncPipe, RouterLink, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule, ImportStatus],
  templateUrl: './import-page.html',
  styleUrl: './import-page.scss',
})
export class ImportPage {
  private readonly importService = inject(ImportService);
  private readonly maxFileSize = inject(IMPORT_MAX_FILE_SIZE);
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
          this.error.set(httpErrorMessage(err, 'Ошибка загрузки файла'));
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
    if (file.size > this.maxFileSize) {
      this.error.set(`Размер файла превышает ${Math.round(this.maxFileSize / 1024 / 1024)} МБ`);
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

