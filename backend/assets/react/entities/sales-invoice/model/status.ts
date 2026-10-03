import type {InvoiceStatus} from '../api/salesInvoiceApi';

/**
 * The kit's row tone for each status (toneFor): a draft waits (warning), an emitted one is open (info), a partly paid
 * one is on its way (accent), a paid one is done (success), a voided one is out (neutral).
 */
const TONE_KEYS: Record<InvoiceStatus, string> = {
  draft: 'prospect',
  emitted: 'order_confirmed',
  partially_paid: 'order_in_transit',
  paid: 'active',
  voided: 'cancelled',
};

export function statusTone(status: string): string {
  return TONE_KEYS[status as InvoiceStatus] ?? 'neutral';
}

/** §4.12: an emitted invoice is voided while no receipt is applied to it. */
export function canVoid(invoice: {
  status: string;
  paid_amount: string;
}): boolean {
  return (
    invoice.status !== 'draft' &&
    invoice.status !== 'voided' &&
    Number(invoice.paid_amount) === 0
  );
}

/** Sent by e-mail: an emitted invoice that was not voided. */
export function canSend(invoice: {status: string}): boolean {
  return invoice.status !== 'draft' && invoice.status !== 'voided';
}
