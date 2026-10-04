type Standing = {status: string; converted_invoice_id?: string | null};

/** Sent by e-mail: an emitted quotation whose offer is still valid. */
export function canSend(q: Standing): boolean {
  return q.status === 'emitted';
}

/** The client's answer: an emitted quotation, even once its offer has lapsed (a late acceptance, decided 2026-10-04). */
export function canDecide(q: Standing): boolean {
  return q.status === 'emitted' || q.status === 'expired';
}

/** §4.7, §9 Q17: once, from an emitted quotation (lapsed or not), or an accepted one that has no invoice yet. */
export function canConvert(q: Standing): boolean {
  return (
    q.status === 'emitted' ||
    q.status === 'expired' ||
    (q.status === 'accepted' && (q.converted_invoice_id ?? null) === null)
  );
}

/** An emitted quotation is voided, valid or lapsed; one that was accepted or rejected is not. */
export function canVoid(q: Standing): boolean {
  return q.status === 'emitted' || q.status === 'expired';
}
