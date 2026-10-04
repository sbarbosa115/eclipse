import {emptyLine} from './draft';
import {computeTotals, type TaxRateInfo} from './totals';
import type {DraftLine} from './types';

const TAXES: TaxRateInfo[] = [
  {id: 'iva19', calculation: 'percentage', rate: '19.0000'},
  {id: 'rete4', calculation: 'percentage', rate: '4.0000'},
  {id: 'none', calculation: 'percentage', rate: '0.0000'},
  {id: 'impo500', calculation: 'per_unit', rate: '500.0000'},
  {
    id: 'reteiva15',
    calculation: 'percentage',
    rate: '15.0000',
    kind: 'reteiva',
  },
];

function line(change: Partial<DraftLine>): DraftLine {
  return {...emptyLine(), ...change};
}

// The same cases as tests/Unit/Shared/DocumentTotalsTest.php: the preview must show the server's numbers.
describe('the totals preview mirrors Shared\\Domain\\Totals\\DocumentTotals', () => {
  it('follows the PRD formula (§4.6)', () => {
    // 2 × 1 000 000 with 10 % off, IVA 19 %, ReteFuente servicios 4 %.
    const totals = computeTotals(
      [
        line({
          quantity: '2',
          unit_price: '1000000',
          discount: '10',
          charge_tax_id: 'iva19',
          withholding_tax_id: 'rete4',
        }),
      ],
      TAXES,
    );

    expect(totals.gross, 'Total bruto = Σ cantidad × valor unitario').toBe(
      '2000000.00',
    );
    expect(totals.discounts, 'Descuentos = Σ bruto × % descuento').toBe(
      '200000.00',
    );
    expect(totals.subtotal, 'Subtotal = bruto − descuentos').toBe('1800000.00');
    expect(totals.taxes, 'IVA on the discounted base').toBe('342000.00');
    expect(totals.withholdings, 'Retención on the discounted base').toBe(
      '72000.00',
    );
    expect(totals.net, 'Total neto = subtotal + impuestos − retenciones').toBe(
      '2070000.00',
    );
  });

  it('rounds once at document level, not per line', () => {
    // Three lines of 0.3333 × 1 at IVA 19 %: per-line rounding would give 3 × 0.06 = 0.18; once gives 0.19.
    const third = {
      quantity: '1',
      unit_price: '0.3333',
      charge_tax_id: 'iva19',
    };
    const totals = computeTotals(
      [line(third), line(third), line(third)],
      TAXES,
    );

    expect(totals.gross, '0.9999 rounds once to 1.00').toBe('1.00');
    expect(totals.taxes, '0.189981 rounds once to 0.19').toBe('0.19');
    expect(totals.net).toBe('1.19');
  });

  it('gives each line its share so the lines add up to the document', () => {
    const third = {
      quantity: '1',
      unit_price: '0.3333',
      charge_tax_id: 'iva19',
    };
    const totals = computeTotals(
      [line(third), line(third), line(third)],
      TAXES,
    );

    expect(
      totals.lines.map((l) => l.subtotal),
      'the cent left over goes to the first line on a tie (largest remainder)',
    ).toEqual(['0.34', '0.33', '0.33']);
    expect(totals.lines.map((l) => l.tax)).toEqual(['0.07', '0.06', '0.06']);
  });

  it('multiplies a tax per unit by the quantity', () => {
    // Impoconsumo por valor: 500 per unit.
    const totals = computeTotals(
      [line({quantity: '3', unit_price: '10000', charge_tax_id: 'impo500'})],
      TAXES,
    );

    expect(totals.taxes).toBe('1500.00');
    expect(totals.net).toBe('31500.00');
  });

  it('takes ReteIVA as a percentage of the line’s IVA, not of its base', () => {
    // Base 1 000 000, IVA 19 % = 190 000; ReteIVA 15 % = 28 500 (as on the server).
    const totals = computeTotals(
      [
        line({
          quantity: '1',
          unit_price: '1000000',
          charge_tax_id: 'iva19',
          withholding_tax_id: 'reteiva15',
        }),
      ],
      TAXES,
    );

    expect(totals.withholdings).toBe('28500.00');
    expect(totals.net).toBe('1161500.00');
  });

  it('is all zeros for an empty document', () => {
    const totals = computeTotals([], TAXES);

    expect(totals.net).toBe('0.00');
    expect(totals.lines).toEqual([]);
  });

  it('counts a half-typed or unknown value as zero instead of failing', () => {
    const totals = computeTotals(
      [
        line({quantity: '2', unit_price: '', charge_tax_id: 'iva19'}),
        line({quantity: '1', unit_price: '100', discount: 'x'}),
        line({quantity: '1', unit_price: '100', charge_tax_id: 'unknown'}),
      ],
      TAXES,
    );

    expect(totals.gross).toBe('200.00');
    expect(totals.discounts, 'an unreadable discount is no discount').toBe(
      '0.00',
    );
    expect(totals.taxes, 'a tax the company no longer offers adds 0').toBe(
      '0.00',
    );
  });

  it('shows a line’s Valor total as its base plus its charge tax (§4.3: an IVA-included price is the line total)', () => {
    // A product of 119 000 with IVA included: the line carries 100 000 net of IVA.
    const totals = computeTotals(
      [
        line({
          quantity: '1',
          unit_price: '100000',
          charge_tax_id: 'iva19',
          withholding_tax_id: 'rete4',
        }),
      ],
      TAXES,
    );

    expect(totals.lines[0]?.lineTotal).toBe('119000.00');
    expect(totals.lines[0]?.total, 'what the line adds to Total neto').toBe(
      '115000.00',
    );
  });
});
