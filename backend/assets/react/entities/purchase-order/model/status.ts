type Standing = {status: string; converted_invoice_id?: string | null};

/** Sent (again) to the supplier by e-mail: an emitted order that was not converted or voided. */
export function canSend(order: Standing): boolean {
  return order.status === 'emitted';
}

/** Once, from an emitted order. */
export function canConvert(order: Standing): boolean {
  return order.status === 'emitted';
}

/** An emitted order is voided; a converted one is not (its invoice is voided instead). */
export function canVoid(order: Standing): boolean {
  return order.status === 'emitted';
}
