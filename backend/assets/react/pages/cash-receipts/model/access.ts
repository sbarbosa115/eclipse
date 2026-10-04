import {can, type Session} from '@/entities/session';

/** Who writes, emits, sends and voids recibos de caja: whoever the server grants WRITE_DOCUMENTS (§8). */
export const canWriteCashReceipts = (
  session: Session | null | undefined,
): boolean => can(session, 'WRITE_DOCUMENTS');
