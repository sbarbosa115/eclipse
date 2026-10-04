import type {QuotationStatus} from '../api/quotationApi';

/**
 * The kit's row tone for each status (toneFor): a draft waits (warning), an emitted one is open (info), an accepted one
 * is won (success), a rejected, expired or voided one is over (neutral).
 */
const TONE_KEYS: Record<QuotationStatus, string> = {
  draft: 'prospect',
  emitted: 'order_confirmed',
  accepted: 'active',
  rejected: 'declined',
  expired: 'order_completed',
  voided: 'cancelled',
};

export function statusTone(status: string): string {
  return TONE_KEYS[status as QuotationStatus] ?? 'neutral';
}

type Standing = {status: string; converted_invoice_id?: string | null};

/** Sent by e-mail: an emitted quotation whose offer is still valid. */
export function canSend(q: Standing): boolean {
  return q.status === 'emitted';
}

/** The client's answer: an emitted quotation whose offer is still valid. */
export function canDecide(q: Standing): boolean {
  return q.status === 'emitted';
}

/** §4.7, §9 Q17: once, from an open emitted quotation, or an accepted one that has no invoice yet. */
export function canConvert(q: Standing): boolean {
  return (
    q.status === 'emitted' ||
    (q.status === 'accepted' && (q.converted_invoice_id ?? null) === null)
  );
}

/** An emitted quotation is voided, valid or lapsed; one that was accepted or rejected is not. */
export function canVoid(q: Standing): boolean {
  return q.status === 'emitted' || q.status === 'expired';
}
