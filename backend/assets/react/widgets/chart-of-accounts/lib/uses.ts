import type {Schema} from '@/shared/api';
import type {Translate} from '@/shared/i18n';

type Moves = Schema<'AccountMovesOutput'>;

/** One thing that points at an account of the chart: a posting rule, a side of a tax, a payment method. */
export interface AccountUse {
  accountId: string;
  kind: 'rule' | 'tax' | 'method';
  /** The rule's concept, or the tax's or method's name. */
  name: string;
}

/** What the settings tabs say points at each account (products and terceros are not listed: the answer counts them). */
export function usesOf(
  rules: {concept: string; account_id: string}[],
  taxes: {
    name: string;
    sales_account_id: string | null;
    purchase_account_id: string | null;
  }[],
  methods: {name: string; account_id: string | null}[],
): AccountUse[] {
  const uses: AccountUse[] = rules.map((r) => ({
    accountId: r.account_id,
    kind: 'rule',
    name: r.concept,
  }));
  for (const tax of taxes) {
    for (const id of [tax.sales_account_id, tax.purchase_account_id]) {
      if (id) uses.push({accountId: id, kind: 'tax', name: tax.name});
    }
  }
  for (const method of methods) {
    if (method.account_id) {
      uses.push({accountId: method.account_id, kind: 'method', name: method.name});
    }
  }
  return uses;
}

/** "a, b y c" */
function listing(t: Translate, parts: string[]): string {
  if (parts.length <= 1) return parts.join('');
  return (
    parts.slice(0, -1).join(', ') +
    t('ledger.chart.subdivide.and') +
    parts[parts.length - 1]
  );
}

function counted(t: Translate, what: string, count: number): string[] {
  if (count === 0) return [];
  return [
    t(`ledger.chart.subdivide.${what}${count === 1 ? 'One' : 'Other'}`, {
      count,
    }),
  ];
}

function rules(t: Translate, concepts: string[]): string[] {
  return concepts.map((concept) =>
    t('ledger.chart.subdivide.rule', {
      name: t(`ledger.rules.concepts.${concept}`),
    }),
  );
}

/**
 * What will move to the first auxiliar of an account, as the warning says it before saving: its rules by name, how
 * many taxes and payment methods, and the products and terceros (counted only by the answer).
 */
export function willMoveText(t: Translate, uses: AccountUse[]): string {
  const taxes = new Set(
    uses.filter((u) => u.kind === 'tax').map((u) => u.name),
  );
  const methods = uses.filter((u) => u.kind === 'method');
  return listing(t, [
    ...rules(
      t,
      uses.filter((u) => u.kind === 'rule').map((u) => u.name),
    ),
    ...counted(t, 'taxes', taxes.size),
    ...counted(t, 'methods', methods.length),
    t('ledger.chart.subdivide.othersUsing'),
  ]);
}

/** What moved, from the answer to adding the account; null when nothing did. */
export function movedText(t: Translate, moved: Moves): string | null {
  const parts = [
    ...rules(t, moved.posting_rules),
    ...counted(t, 'taxes', moved.taxes),
    ...counted(t, 'methods', moved.payment_methods),
    ...counted(t, 'products', moved.products),
    ...counted(t, 'terceros', moved.terceros),
  ];
  return parts.length === 0 ? null : listing(t, parts);
}
