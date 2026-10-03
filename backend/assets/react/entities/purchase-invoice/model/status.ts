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

/**
 * The kit's row tint for a status (tables have no status column, the colour is the status): a draft waits for the
 * user, a partly paid one is in progress, a paid one is done, a voided one is over. An emitted, unpaid one is plain.
 */
const ROW_TONES: Record<string, string | null> = {
  draft: 'prospect',
  emitted: null,
  partially_paid: 'order_confirmed',
  paid: 'active',
  voided: 'cancelled',
};

export function rowStatus(status: string): string | null {
  return ROW_TONES[status] ?? null;
}

/** §4.12: an emitted invoice is voided only while no payment is allocated to it. */
export function canVoid(invoice: {status: string; paid_amount: string}): boolean {
  return invoice.status === 'emitted' && Number(invoice.paid_amount) === 0;
}

/** Who creates, emits and voids purchase documents: the owner and billing users (the accountant reads, §8). */
export const canWritePurchases = (role: string | undefined): boolean =>
  role === 'owner' || role === 'billing';

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
