import {can, type Session} from '@/entities/session';

/** Who pays, sends and voids recibos de pago: whoever the server grants WRITE_DOCUMENTS (§8). */
export const canWriteSupplierPayments = (
  session: Session | null | undefined,
): boolean => can(session, 'WRITE_DOCUMENTS');
