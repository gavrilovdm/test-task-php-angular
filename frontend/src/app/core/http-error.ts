import { HttpErrorResponse } from '@angular/common/http';

import { ApiError } from '../models';

/** Human-readable message from an API error: field errors, then the "error" field, then the fallback. */
export function httpErrorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof HttpErrorResponse)) {
    return fallback;
  }
  const body = error.error as Partial<ApiError> | null;
  if (body?.errors && Object.keys(body.errors).length > 0) {
    return Object.values(body.errors).join('; ');
  }
  return body?.error ?? `${fallback} (HTTP ${error.status})`;
}
