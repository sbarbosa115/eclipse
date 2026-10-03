import {ApiError} from '@/shared/api';
import type {Translate} from '@/shared/i18n';

/** What a refused request says to the person: the ledger's own error codes, else the API's violation, else generic. */
export function errorMessage(t: Translate, error: unknown): string {
  if (!(error instanceof ApiError)) return t('common.errors.network');
  const key = `ledger.errors.${error.code}`;
  const known = t(key);
  if (error.code === 'validation_failed') {
    const body = error.body as {violations?: {message: string}[]};
    return body.violations?.[0]?.message ?? known;
  }
  return known === key ? t('common.errors.unexpected') : known;
}
