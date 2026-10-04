import {ApiError} from '@/shared/api';
import type {Translate} from '@/shared/i18n';
import {formatDate} from '@/shared/lib';

export const STATUSES = [
  'draft',
  'emitted',
  'partially_paid',
  'paid',
  'voided',
] as const;

/** §4.12: an emitted invoice is voided only while no payment is allocated to it. */
export function canVoid(invoice: {
  status: string;
  paid_amount: string;
}): boolean {
  return invoice.status === 'emitted' && Number(invoice.paid_amount) === 0;
}

/** Who creates, emits and voids purchase documents: whoever the server grants WRITE_DOCUMENTS (§8). */
// (A session's shape, not the session entity: entities do not import each other.)
export const canWritePurchases = (
  session: {permissions?: string[]} | null | undefined,
): boolean => session?.permissions?.includes('WRITE_DOCUMENTS') ?? false;

/** What went wrong, in words: by the API's error code, never by its (developer) message. */
export function purchaseErrorMessage(error: unknown, t: Translate): string {
  if (error instanceof ApiError) {
    if (error.code === 'period_locked') {
      const detail = (error.body as {detail?: {locked_until?: string}} | null)
        ?.detail;
      return t('purchaseInvoice.errors.period_locked', {
        date: formatDate(detail?.locked_until ?? ''),
      });
    }
    const key = `purchaseInvoice.errors.${error.code}`;
    const text = t(key);
    if (text !== key) return text;
    if (error.status === 403) return t('purchaseInvoice.errors.forbidden');
  }
  return t('purchaseInvoice.errors.unexpected');
}
