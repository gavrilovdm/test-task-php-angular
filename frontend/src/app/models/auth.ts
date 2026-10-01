export interface User {
  email: string;
}

export interface LoginResponse {
  token: string;
  expiresAt: string;
  user: User;
}

export interface ApiError {
  error: string;
  errors?: Record<string, string>;
  retryAfter?: number;
}
