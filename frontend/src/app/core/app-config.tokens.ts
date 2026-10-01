import { InjectionToken } from '@angular/core';

/** Base URL of the backend API. */
export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL', { factory: () => '/api' });

/**
 * Client-side pre-check of the upload size (fast feedback without uploading).
 * The backend (IMPORT_MAX_FILE_SIZE) remains the source of truth.
 */
export const IMPORT_MAX_FILE_SIZE = new InjectionToken<number>('IMPORT_MAX_FILE_SIZE', { factory: () => 10 * 1024 * 1024 });
