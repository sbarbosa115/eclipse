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
