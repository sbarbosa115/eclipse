import {apiPost, type Schema} from '@/shared/api';

export type Invitation = Schema<'InvitationOutput'>;
type Session = Schema<'SessionOutput'>;

export function lookupInvitation(token: string): Promise<Invitation> {
  return apiPost<Invitation>('/auth/invitations/lookup', {token});
}

export function acceptInvitation(
  token: string,
  name: string,
  password: string,
): Promise<Session> {
  return apiPost<Session>('/auth/invitations/accept', {token, name, password});
}
