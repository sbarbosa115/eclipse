import {can, type Session} from '@/entities/session';

/** Who writes, emits, sends and voids sales invoices: whoever the server grants WRITE_DOCUMENTS (§8). */
export const canWriteSalesInvoices = (
  session: Session | null | undefined,
): boolean => can(session, 'WRITE_DOCUMENTS');
