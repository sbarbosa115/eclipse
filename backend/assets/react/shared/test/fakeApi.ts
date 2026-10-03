import {vi} from 'vitest';

type Answer = [status: number, body?: unknown];
type Handler = Answer | ((body: unknown, url: URL) => Answer);

/**
 * Stubs fetch with answers by "METHOD /path" (without /api/v1 and the query string). Every call is recorded; a call
 * nobody answers fails the test loudly with a 599.
 */
export function fakeApi(routes: Record<string, Handler>) {
  const calls: {method: string; path: string; url: URL; body: unknown}[] = [];
  const fetch = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = new URL(String(input), 'http://localhost');
    const method = init?.method ?? 'GET';
    const path = url.pathname.replace(/^\/api\/v1/, '');
    const body = init?.body ? JSON.parse(String(init.body)) : undefined;
    calls.push({method, path, url, body});
    const handler = routes[`${method} ${path}`];
    const [status, answer] =
      typeof handler === 'function'
        ? handler(body, url)
        : (handler ?? [599, {error: `unexpected ${method} ${path}`}]);
    return new Response(answer === undefined ? null : JSON.stringify(answer), {
      status,
    });
  });
  vi.stubGlobal('fetch', fetch);
  return {calls, fetch};
}
