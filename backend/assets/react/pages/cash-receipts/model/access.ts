/** Who receives, sends and voids recibos de caja: the owner and billing users; the accountant reads (§8). */
export const canWriteCashReceipts = (role: string | undefined) =>
  role === 'owner' || role === 'billing';
