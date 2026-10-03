import {apiGet, apiPost, apiPut, type Schema} from '@/shared/api';

export type CompanyUser = Schema<'UserOutput'>;
export type UserRole = 'owner' | 'billing' | 'accountant';

export async function listUsers(): Promise<CompanyUser[]> {
  return (await apiGet<{items: CompanyUser[]}>('/users')).items;
}

export function resendInvitation(id: string): Promise<CompanyUser> {
  return apiPost<CompanyUser>(`/users/${id}/invitation`, {});
}

export function changeRole(id: string, role: UserRole): Promise<CompanyUser> {
  return apiPut<CompanyUser>(`/users/${id}/role`, {role});
}

export function setUserActive(
  id: string,
  active: boolean,
): Promise<CompanyUser> {
  return apiPost<CompanyUser>(
    `/users/${id}/${active ? 'reactivate' : 'deactivate'}`,
    {},
  );
}
