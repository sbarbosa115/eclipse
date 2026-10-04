/**
 * Who pays, sends and voids recibos de pago: the owner, billing and the accountant (decided 2026-10-04). One place, so
 * it can follow the server-sent permission list later.
 */
export const canWriteSupplierPayments = (role: string | undefined) =>
  role === 'owner' || role === 'billing' || role === 'accountant';
