import {apiGet, apiPut, type Schema} from '@/shared/api';

export type PostingRule = Schema<'PostingRuleOutput'>;
export type AccountOption = Schema<'AccountOutput'>;
export type LockDate = Schema<'LockDateOutput'>;

export function fetchRules() {
  return apiGet<{items: PostingRule[]}>('/posting-rules');
}

export function changeRule(concept: string, accountId: string) {
  return apiPut<PostingRule>(`/posting-rules/${concept}`, {
    account_id: accountId,
  });
}

export function searchAccounts(q: string) {
  return apiGet<{items: AccountOption[]}>(
    `/accounts/search?q=${encodeURIComponent(q)}`,
  );
}

export function fetchLockDate() {
  return apiGet<LockDate>('/ledger/lock-date');
}

export function moveLockDate(lockedUntil: string) {
  return apiPut<LockDate>('/ledger/lock-date', {locked_until: lockedUntil});
}
