import {ApiError} from '@/shared/api';
import type {Translate} from '@/shared/i18n';
import type {FormErrors} from '../model/form';

/** The violations an API error carries, by field (`identification_number`, `contacts[0].name`). */
export function violationsOf(error: unknown): FormErrors {
  const found: FormErrors = {};
  if (!(error instanceof ApiError)) return found;
  const body = error.body as {
    violations?: {field: string; message: string}[];
  } | null;
  for (const violation of body?.violations ?? []) {
    found[violation.field] ??= violation.message;
  }
  return found;
}

/** What went wrong, in words: by the API's error code, never by its (developer) message. */
export function terceroErrorMessage(error: unknown, t: Translate): string {
  if (error instanceof ApiError) {
    const key = `terceros.errors.${error.code}`;
    const text = t(key);
    if (text !== key) return text;
    if (error.status === 403) return t('terceros.errors.forbidden');
  }
  return t('terceros.errors.unexpected');
}

/** The client-side validation keys (`required`, `email`…) in words; anything else is already a sentence. */
export function describeErrors(errors: FormErrors, t: Translate): FormErrors {
  const described: FormErrors = {};
  for (const [field, key] of Object.entries(errors)) {
    const text = t(`terceros.validation.${key}`);
    described[field] = text === `terceros.validation.${key}` ? key : text;
  }
  return described;
}
