// The JSON API client. Same origin: Symfony serves both the app and /api/v1.
export const API_BASE = '/api/v1';

/** Dispatched on `window` when a call answers 401: whoever was signed in no longer is. */
export const SIGNED_OUT_EVENT = 'mustang:signed-out';

/** An answer outside 2xx. `code` is the API's machine-readable "error". */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly body: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : {'Content-Type': 'application/json'}),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await response.text();
  const data: unknown = text === '' ? null : JSON.parse(text);
  if (!response.ok) {
    const error = (data ?? {}) as {error?: string; message?: string};
    if (
      response.status === 401 &&
      path !== '/me' &&
      !path.startsWith('/auth/')
    ) {
      // The session ended (two hours idle, deactivated, password changed elsewhere): the session provider hears it
      // and the app shows the sign-in page. ("Who is signed in?" is how the provider asks, so it is not counted.)
      window.dispatchEvent(new Event(SIGNED_OUT_EVENT));
    }
    throw new ApiError(
      response.status,
      error.error ?? 'http_error',
      error.message ?? response.statusText,
      data,
    );
  }
  return data as T;
}

export function apiGet<T>(path: string): Promise<T> {
  return request<T>('GET', path);
}

export function apiPost<T>(path: string, body: unknown): Promise<T> {
  return request<T>('POST', path, body);
}

export function apiPut<T>(path: string, body: unknown): Promise<T> {
  return request<T>('PUT', path, body);
}

export function apiDelete<T = null>(path: string): Promise<T> {
  return request<T>('DELETE', path);
}
