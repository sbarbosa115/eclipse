import {apiGet, type Schema} from '@/shared/api';

export type JournalEntry = Schema<'JournalEntryOutput'>;
export type TrialBalance = Schema<'TrialBalanceOutput'>;
export type TrialBalanceRow = Schema<'TrialBalanceRowOutput'>;
export type IncomeStatement = Schema<'IncomeStatementOutput'>;
export type BalanceSheet = Schema<'BalanceSheetOutput'>;
export type StatementSection = Schema<'StatementSectionOutput'>;

export interface JournalPage {
  items: JournalEntry[];
  total: number;
  page: number;
  per_page: number;
}

const query = (params: Record<string, string | undefined>) => {
  const search = new URLSearchParams();
  for (const [name, value] of Object.entries(params)) {
    if (value) search.set(name, value);
  }
  const text = search.toString();
  return text === '' ? '' : `?${text}`;
};

export interface JournalQuery {
  from?: string;
  to?: string;
  account?: string;
  tercero_id?: string;
  page?: string;
}

export function fetchJournal(params: JournalQuery) {
  return apiGet<JournalPage>(`/ledger/journal${query({...params})}`);
}

export function fetchTrialBalance(from?: string, to?: string) {
  return apiGet<TrialBalance>(`/ledger/trial-balance${query({from, to})}`);
}

export function fetchIncomeStatement(from?: string, to?: string) {
  return apiGet<IncomeStatement>(
    `/ledger/income-statement${query({from, to})}`,
  );
}

export function fetchBalanceSheet(date?: string) {
  return apiGet<BalanceSheet>(`/ledger/balance-sheet${query({date})}`);
}
