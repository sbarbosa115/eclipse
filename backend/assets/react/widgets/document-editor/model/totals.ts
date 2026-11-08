import {Decimal} from '../lib/decimal';
import type {DraftLine} from './types';

/** What the preview needs of a tax: how it computes and its rate (a percentage, or a value per unit). */
export interface TaxRateInfo {
  id: string;
  /** Shown in the totals panel's row of each retención. */
  name?: string;
  calculation: string;
  rate: string;
  /** The tax's kind (`reteiva`…): a ReteIVA is a percentage of the line's IVA, not of its base. */
  kind?: string;
}

/** One line's share of the totals, in pesos with two decimals. */
export interface LineAmounts {
  gross: string;
  discount: string;
  subtotal: string;
  tax: string;
  /** Σ of the line's retenciones. */
  withholding: string;
  /** One share per retención the preview knows, in the line's order (the server's LineAmounts::$withholdings). */
  withholdings: string[];
  /** What the line adds to Total neto: subtotal + impuesto − retenciones (the server's LineAmounts::total). */
  total: string;
  /** The grid's Valor total: subtotal + impuesto cargo, so an IVA-included price is the line total (§4.3). */
  lineTotal: string;
}

/** A retención summed over the document: its base (subtotals, or IVA for ReteIVA) and its value (WithholdingSummaryView). */
export interface WithholdingByTax {
  tax_id: string;
  name: string;
  base: string;
  amount: string;
}

export interface TotalsPreview {
  gross: string;
  discounts: string;
  subtotal: string;
  taxes: string;
  withholdings: string;
  net: string;
  lines: LineAmounts[];
  /** One row per retención tax, in the order each first appears; their amounts add up to `withholdings`. */
  withholdingsByTax: WithholdingByTax[];
}

const MONEY_SCALE = 2;
const CENT = Decimal.of('0.01');

/** A typed value, or zero while it is empty or half-typed: the preview never fails on what is being written. */
function read(text: string): Decimal {
  return Decimal.parse(text) ?? Decimal.ZERO;
}

function taxOn(
  tax: TaxRateInfo | undefined,
  base: Decimal,
  quantity: Decimal,
  chargeTax: Decimal = Decimal.ZERO,
): Decimal {
  if (!tax) return Decimal.ZERO;
  const rate = read(tax.rate);
  if (tax.calculation === 'per_unit') return quantity.times(rate);
  // ReteIVA: a percentage of the line's IVA (TaxBase::ChargeTax on the server).
  return (tax.kind === 'reteiva' ? chargeTax : base).times(rate).percent();
}

/**
 * Splits the rounded sum of exact values into cents per value, as DocumentTotals::shares() does: each value rounded
 * down, then the cents left over go one each to the values with the largest remainders (the first one wins a tie).
 */
function shares(exact: Decimal[]): Decimal[] {
  if (exact.length === 0) return [];
  const total = Decimal.sum(exact).roundHalfUp(MONEY_SCALE);
  const floors = exact.map((v) => v.floor(MONEY_SCALE));
  const left = Number(total.minus(Decimal.sum(floors)).toUnits(MONEY_SCALE));
  const remainders = exact.map((v, i) => v.minus(floors[i] as Decimal));
  const order = exact
    .map((_, i) => i)
    .sort(
      (a, b) =>
        (remainders[b] as Decimal).compare(remainders[a] as Decimal) || a - b,
    );
  for (let k = 0; k < left; k++) {
    const i = order[k % order.length] as number;
    floors[i] = (floors[i] as Decimal).plus(CENT);
  }
  return floors;
}

/**
 * The totals of a document (§4.6), computed exactly from the lines as typed and rounded once per total, half up: the
 * same numbers Shared\Domain\Totals\DocumentTotals gives on the server. A preview only: the server's are what is saved.
 *
 *     Total bruto  = Σ cantidad × valor unitario
 *     Descuentos   = Σ cantidad × valor unitario × % descuento
 *     Subtotal     = Total bruto − Descuentos
 *     Impuestos    = Σ impuesto cargo de cada línea, sobre la base con descuento
 *     Retenciones  = Σ retenciones de cada línea (ReteIVA sobre el IVA de la línea)
 *     Total neto   = Subtotal + Impuestos − Retenciones
 *
 * Retenciones are shared per (line, retención) pair, as on the server, so each one's amount (what posts to its own
 * account) adds up to the document's Retenciones. A tax the company no longer offers, and "Ninguno", are left out.
 */
export function computeTotals(
  lines: ReadonlyArray<DraftLine>,
  taxes: ReadonlyArray<TaxRateInfo>,
): TotalsPreview {
  const byId = new Map(taxes.map((tax) => [tax.id, tax]));
  const gross: Decimal[] = [];
  const discount: Decimal[] = [];
  const tax: Decimal[] = [];
  const withholding: Decimal[] = [];
  /** Per (line, retención) pair: its line and its tax. */
  const pairs: {line: number; tax: TaxRateInfo}[] = [];

  lines.forEach((line, i) => {
    const quantity = read(line.quantity);
    const lineGross = quantity.times(read(line.unit_price));
    const lineDiscount = lineGross.times(read(line.discount).percent());
    const base = lineGross.minus(lineDiscount);
    gross.push(lineGross);
    discount.push(lineDiscount);
    const lineTax = taxOn(byId.get(line.charge_tax_id ?? ''), base, quantity);
    tax.push(lineTax);
    for (const id of line.withholdings) {
      const rate = byId.get(id);
      // A tax the company no longer offers, or "Ninguno" (a no-tax snapshot the server skips), is no retención.
      if (!rate || rate.kind === 'none') continue;
      withholding.push(taxOn(rate, base, quantity, lineTax));
      pairs.push({line: i, tax: rate});
    }
  });

  const grossShares = shares(gross);
  const discountShares = shares(discount);
  const taxShares = shares(tax);
  const withholdingShares = shares(withholding);
  const perLine: Decimal[][] = lines.map(() => []);
  withholdingShares.forEach((share, k) => {
    perLine[(pairs[k] as {line: number}).line]?.push(share);
  });

  const amounts = lines.map((_, i): LineAmounts => {
    const g = grossShares[i] as Decimal;
    const d = discountShares[i] as Decimal;
    const tx = taxShares[i] as Decimal;
    const own = perLine[i] ?? [];
    const w = Decimal.sum(own).roundHalfUp(MONEY_SCALE);
    const subtotal = g.minus(d);
    return {
      gross: g.toString(),
      discount: d.toString(),
      subtotal: subtotal.toString(),
      tax: tx.toString(),
      withholding: w.toString(),
      withholdings: own.map((share) => share.toString()),
      total: subtotal.plus(tx).minus(w).toString(),
      lineTotal: subtotal.plus(tx).toString(),
    };
  });

  // By tax, as WithholdingSummaryView: the base is the line's subtotal share, or its IVA share for a ReteIVA.
  const byTax = new Map<
    string,
    {name: string; base: Decimal; amount: Decimal}
  >();
  withholdingShares.forEach((share, k) => {
    const {line, tax: rate} = pairs[k] as {line: number; tax: TaxRateInfo};
    const base =
      rate.kind === 'reteiva'
        ? (taxShares[line] as Decimal)
        : (grossShares[line] as Decimal).minus(discountShares[line] as Decimal);
    const group = byTax.get(rate.id) ?? {
      name: rate.name ?? '',
      base: Decimal.ZERO,
      amount: Decimal.ZERO,
    };
    byTax.set(rate.id, {
      name: group.name,
      base: group.base.plus(base),
      amount: group.amount.plus(share),
    });
  });

  const money = (values: Decimal[]) =>
    Decimal.sum(values).roundHalfUp(MONEY_SCALE);
  const totalGross = money(grossShares);
  const totalDiscounts = money(discountShares);
  const subtotal = totalGross.minus(totalDiscounts);
  const totalTaxes = money(taxShares);
  const totalWithholdings = money(withholdingShares);

  return {
    gross: totalGross.toString(),
    discounts: totalDiscounts.toString(),
    subtotal: subtotal.toString(),
    taxes: totalTaxes.toString(),
    withholdings: totalWithholdings.toString(),
    net: subtotal.plus(totalTaxes).minus(totalWithholdings).toString(),
    lines: amounts,
    withholdingsByTax: [...byTax].map(([taxId, group]) => ({
      tax_id: taxId,
      name: group.name,
      base: group.base.roundHalfUp(MONEY_SCALE).toString(),
      amount: group.amount.roundHalfUp(MONEY_SCALE).toString(),
    })),
  };
}
