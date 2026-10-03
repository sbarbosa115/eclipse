import {ApiError} from '@/shared/api';

/** The server's refusal of a form, by field: {code: 'Ya hay un producto…'}. Empty when it is not a validation error. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.status !== 422) return {};
  const body = error.body as {
    violations?: {field: string; message: string}[];
  } | null;
  const found: Record<string, string> = {};
  for (const violation of body?.violations ?? []) {
    found[violation.field] ??= violation.message;
  }
  return found;
}
