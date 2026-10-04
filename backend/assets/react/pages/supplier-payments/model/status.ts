/** The kit's row tone for each status: an emitted payment is in the books (info), a voided one is out (neutral). */
export function paymentTone(status: string): string {
  return status === 'voided' ? 'cancelled' : 'order_confirmed';
}
