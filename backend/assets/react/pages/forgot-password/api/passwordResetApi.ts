import {apiPost} from '@/shared/api';

/** Always 202, whoever asks: the page says the same thing either way. */
export async function requestPasswordReset(email: string): Promise<void> {
  await apiPost<null>('/auth/password-reset', {email});
}
