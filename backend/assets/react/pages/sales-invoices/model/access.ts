/** Who writes, emits, sends and voids sales invoices: the owner and billing users; the accountant reads (§8). */
export const canWriteSalesInvoices = (role: string | undefined) =>
  role === 'owner' || role === 'billing';
