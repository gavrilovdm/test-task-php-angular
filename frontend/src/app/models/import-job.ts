export type ImportJobStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ImportError {
  row: number | null;
  externalCode: string | null;
  field: string | null;
  message: string;
  level: 'error' | 'warning';
}

export interface ImportJob {
  id: string;
  status: ImportJobStatus;
  fileName: string;
  progress: number;
  totalRows: number;
  processedRows: number;
  createdCount: number;
  updatedCount: number;
  failedCount: number;
  errors: ImportError[];
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export function isImportFinished(job: ImportJob): boolean {
  return job.status === 'completed' || job.status === 'failed';
}
