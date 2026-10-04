import type {Session} from '../api/sessionApi';

/**
 * What a person may do, as the server names it (Shared\UI\Http\Security\Permission). The server sends the list with
 * the session, so a screen asks `can(session, 'WRITE_DOCUMENTS')` and never compares role names: who may do what is
 * decided in one place, the server's matrix.
 */
export type Permission =
  | 'MANAGE_USERS'
  | 'MANAGE_SETTINGS'
  | 'MANAGE_BOOKS'
  | 'VIEW_BOOKS'
  | 'WRITE_DOCUMENTS'
  | 'READ_DOCUMENTS';

export function can(
  session: Pick<Session, 'permissions'> | null | undefined,
  permission: Permission,
): boolean {
  return session?.permissions?.includes(permission) ?? false;
}
