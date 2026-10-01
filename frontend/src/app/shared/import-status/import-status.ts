import { DatePipe } from '@angular/common';
import { Component, computed, input } from '@angular/core';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';

import { ImportJob, ImportJobStatus, isImportFinished } from '../../models';

const STATUS_LABELS: Record<ImportJobStatus, string> = {
  pending: 'В очереди',
  processing: 'Выполняется',
  completed: 'Завершён',
  failed: 'Ошибка',
};

/** Import progress indicator + error report of an import job. */
@Component({
  selector: 'app-import-status',
  imports: [DatePipe, MatCardModule, MatChipsModule, MatIconModule, MatProgressBarModule, MatTableModule],
  templateUrl: './import-status.html',
  styleUrl: './import-status.scss',
})
export class ImportStatus {
  readonly job = input.required<ImportJob>();
  readonly compact = input(false);

  protected readonly columns = ['level', 'row', 'externalCode', 'field', 'message'];
  protected readonly statusLabel = computed(() => STATUS_LABELS[this.job().status]);
  protected readonly finished = computed(() => isImportFinished(this.job()));
  protected readonly errorCount = computed(() => this.job().errors.filter((e) => e.level === 'error').length);
  protected readonly warningCount = computed(() => this.job().errors.filter((e) => e.level === 'warning').length);
}
