import {apiPost, type Schema} from '@/shared/api';

type Session = Schema<'SessionOutput'>;

/** Resolves while the link works; 404 `link_invalid` otherwise. */
export async function checkResetLink(token: string): Promise<void> {
  await apiPost<null>('/auth/password-reset/check', {token});
}

export function resetPassword(
  token: string,
  password: string,
): Promise<Session> {
  return apiPost<Session>('/auth/password-reset/confirm', {token, password});
}
