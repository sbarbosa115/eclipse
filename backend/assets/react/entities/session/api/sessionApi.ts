import {apiGet, apiPost, type Schema} from '@/shared/api';

export type Session = Schema<'SessionOutput'>;
export type Role = 'owner' | 'billing' | 'accountant';

export function fetchSession(): Promise<Session> {
  return apiGet<Session>('/me');
}

export function signIn(email: string, password: string): Promise<Session> {
  return apiPost<Session>('/auth/sign-in', {email, password});
}

export interface SignUpData {
  company_name: string;
  nit: string;
  owner_name: string;
  email: string;
  password: string;
}

export function signUp(data: SignUpData): Promise<Session> {
  return apiPost<Session>('/auth/sign-up', data);
}

export async function signOut(): Promise<void> {
  await apiPost<null>('/auth/sign-out', {});
}
