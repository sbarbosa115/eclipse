import {API_BASE} from '@/shared/api';

export type ExportFormat = 'csv' | 'pdf';

/**
 * The address of a report's file: the API's export endpoint with the report's own filters and the format. A plain GET
 * on the same origin, so the session cookie goes along and the browser saves the file the server names.
 */
export function exportUrl(
  path: string,
  format: ExportFormat,
  params: Record<string, string | undefined> = {},
): string {
  const search = new URLSearchParams();
  for (const [name, value] of Object.entries(params)) {
    if (value) search.set(name, value);
  }
  search.set('format', format);
  return `${API_BASE}${path}?${search.toString()}`;
}
