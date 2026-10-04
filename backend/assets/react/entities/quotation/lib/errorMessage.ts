import type {Translate} from '@/shared/i18n';
import {ApiError} from '@/shared/api';

/** What to tell the person when a quotation call fails: the API's error code in words. */
export function quotationErrorMessage(error: unknown, t: Translate): string {
  if (!(error instanceof ApiError)) {
    return t(
      error instanceof TypeError
        ? 'common.errors.network'
        : 'quotation.errors.unexpected',
    );
  }
  const key = `quotation.errors.${error.code}`;
  const message = t(key);
  return message === key ? t('quotation.errors.unexpected') : message;
}

export interface Violation {
  field: string;
  message: string;
}

/** The field-by-field problems of a 422 `validation_failed`; empty for any other error. */
export function violationsOf(error: unknown): Violation[] {
  if (!(error instanceof ApiError) || error.code !== 'validation_failed') {
    return [];
  }
  return (error.body as {violations?: Violation[]} | null)?.violations ?? [];
}
