import {apiPost, type Schema} from '@/shared/api';

export type InvitedUser = Schema<'UserOutput'>;
export type InvitableRole = 'billing' | 'accountant';

export function inviteUser(
  email: string,
  role: InvitableRole,
): Promise<InvitedUser> {
  return apiPost<InvitedUser>('/users/invitations', {email, role});
}
