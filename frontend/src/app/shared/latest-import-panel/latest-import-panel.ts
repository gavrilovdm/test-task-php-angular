import { AsyncPipe } from '@angular/common';
import { Component, inject } from '@angular/core';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatIconModule } from '@angular/material/icon';
import { Observable, catchError, of, switchMap } from 'rxjs';

import { ImportJob, isImportFinished } from '../../models';
import { ImportService } from '../../services/import.service';
import { ImportStatus } from '../import-status/import-status';

/** Collapsible panel with the latest import job; polls it while it is still running. */
@Component({
  selector: 'app-latest-import-panel',
  imports: [AsyncPipe, MatExpansionModule, MatIconModule, ImportStatus],
  template: `
    @if (job$ | async; as job) {
      <mat-expansion-panel [expanded]="!isFinished(job)" data-testid="latest-import">
        <mat-expansion-panel-header>
          <mat-panel-title><mat-icon>sync</mat-icon>&nbsp;Последний импорт</mat-panel-title>
          <mat-panel-description>{{ job.progress }}% · {{ job.fileName }}</mat-panel-description>
        </mat-expansion-panel-header>
        <app-import-status [job]="job" />
      </mat-expansion-panel>
    }
  `,
})
export class LatestImportPanel {
  private readonly importService = inject(ImportService);

  protected readonly isFinished = isImportFinished;

  protected readonly job$: Observable<ImportJob | null> = this.importService.getLatest(1).pipe(
    switchMap(([job]) => (!job || isImportFinished(job) ? of(job ?? null) : this.importService.watch(job.id))),
    catchError(() => of(null)),
  );
}
