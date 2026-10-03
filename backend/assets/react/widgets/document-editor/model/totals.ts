import {Decimal} from '../lib/decimal';
import type {DraftLine} from './types';

/** What the preview needs of a tax: how it computes and its rate (a percentage, or a value per unit). */
export interface TaxRateInfo {
  id: string;
  calculation: string;
  rate: string;
}

/** One line's share of the totals, in pesos with two decimals. */
export interface LineAmounts {
  gross: string;
  discount: string;
  subtotal: string;
  tax: string;
  withholding: string;
  /** What the line adds to Total neto: subtotal + impuesto − retención (the server's LineAmounts::total). */
  total: string;
  /** The grid's Valor total: subtotal + impuesto cargo, so an IVA-included price is the line total (§4.3). */
  lineTotal: string;
}

export interface TotalsPreview {
  gross: string;
  discounts: string;
  subtotal: string;
  taxes: string;
  withholdings: string;
  net: string;
  lines: LineAmounts[];
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
): Decimal {
  if (!tax) return Decimal.ZERO;
  const rate = read(tax.rate);
  return tax.calculation === 'per_unit'
    ? quantity.times(rate)
    : base.times(rate).percent();
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
 *     Retenciones  = Σ impuesto retención de cada línea
 *     Total neto   = Subtotal + Impuestos − Retenciones
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

  for (const line of lines) {
    const quantity = read(line.quantity);
    const lineGross = quantity.times(read(line.unit_price));
    const lineDiscount = lineGross.times(read(line.discount).percent());
    const base = lineGross.minus(lineDiscount);
    gross.push(lineGross);
    discount.push(lineDiscount);
    tax.push(taxOn(byId.get(line.charge_tax_id ?? ''), base, quantity));
    withholding.push(
      taxOn(byId.get(line.withholding_tax_id ?? ''), base, quantity),
    );
  }

  const grossShares = shares(gross);
  const discountShares = shares(discount);
  const taxShares = shares(tax);
  const withholdingShares = shares(withholding);

  const amounts = lines.map((_, i): LineAmounts => {
    const g = grossShares[i] as Decimal;
    const d = discountShares[i] as Decimal;
    const tx = taxShares[i] as Decimal;
    const w = withholdingShares[i] as Decimal;
    const subtotal = g.minus(d);
    return {
      gross: g.toString(),
      discount: d.toString(),
      subtotal: subtotal.toString(),
      tax: tx.toString(),
      withholding: w.toString(),
      total: subtotal.plus(tx).minus(w).toString(),
      lineTotal: subtotal.plus(tx).toString(),
    };
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
  };
}
