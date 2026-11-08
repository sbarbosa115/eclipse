import {can, type Session} from '@/entities/session';

/** Who writes, emits, sends, voids and converts purchase orders: whoever the server grants WRITE_DOCUMENTS (§8). */
export const canWritePurchaseOrders = (
  session: Session | null | undefined,
): boolean => can(session, 'WRITE_DOCUMENTS');
