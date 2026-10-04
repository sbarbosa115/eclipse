// The arithmetic of allocating money received (or paid) to open items: exact, in cents (BigInt), never floats
// (CLAUDE.md). What is posted is the server's; this only keeps the person's running difference honest.
import {parseAmount} from '@/shared/lib';

/** One open receivable or payable, as the allocation table shows it. */
export interface OpenItem {
  id: string;
  /** The invoice's number. */
  document: string;
  issueDate: string;
  dueDate: string;
  amount: string;
  /** What is still owed: the most an allocation can take. */
  balance: string;
}

export type RowError = 'invalid' | 'exceeds';

export interface AllocationSummary {
  /** Σ of the rows' amounts, a decimal string. */
  allocated: string;
  /** Amount received − allocated: negative when more was allocated. */
  difference: string;
  /** Something received, every row valid, and the difference zero: ready to save. */
  balanced: boolean;
  /** By item id. */
  errors: Record<string, RowError>;
}

function toCents(decimal: string): bigint {
  const negative = decimal.startsWith('-');
  const [whole = '0', fraction = ''] = decimal.replace('-', '').split('.');
  const cents = BigInt(whole || '0') * 100n + BigInt(fraction.padEnd(2, '0'));
  return negative ? -cents : cents;
}

function fromCents(cents: bigint): string {
  const negative = cents < 0n;
  const magnitude = negative ? -cents : cents;
  const whole = magnitude / 100n;
  const fraction = String(magnitude % 100n).padStart(2, '0');
  return `${negative ? '-' : ''}${whole}.${fraction}`;
}

/** The running difference (§4.9: allocations must add up to the amount received) and each row's problem. */
export function summarize(
  total: string,
  items: ReadonlyArray<OpenItem>,
  amounts: Readonly<Record<string, string>>,
): AllocationSummary {
  const received = parseAmount(total);
  const errors: Record<string, RowError> = {};
  let allocated = 0n;
  for (const item of items) {
    const text = amounts[item.id] ?? '';
    if (text.trim() === '') continue;
    const value = parseAmount(text);
    if (value === null) {
      errors[item.id] = 'invalid';
      continue;
    }
    const cents = toCents(value);
    if (cents > toCents(item.balance)) errors[item.id] = 'exceeds';
    allocated += cents;
  }
  const receivedCents = received === null ? 0n : toCents(received);
  const difference = receivedCents - allocated;
  return {
    allocated: fromCents(allocated),
    difference: fromCents(difference),
    balanced:
      receivedCents > 0n &&
      difference === 0n &&
      Object.keys(errors).length === 0,
    errors,
  };
}

/** What the API is sent: the rows with an amount above zero, as decimal strings. */
export function allocationsOf(
  items: ReadonlyArray<OpenItem>,
  amounts: Readonly<Record<string, string>>,
): {id: string; amount: string}[] {
  const rows: {id: string; amount: string}[] = [];
  for (const item of items) {
    const value = parseAmount(amounts[item.id] ?? '');
    if (value !== null && toCents(value) > 0n) {
      rows.push({id: item.id, amount: value});
    }
  }
  return rows;
}
