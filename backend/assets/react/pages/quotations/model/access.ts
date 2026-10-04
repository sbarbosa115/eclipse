/** Who writes, emits, sends, answers and voids quotations: the owner and billing users; the accountant reads (§8). */
export const canWriteQuotations = (role: string | undefined) =>
  role === 'owner' || role === 'billing';
