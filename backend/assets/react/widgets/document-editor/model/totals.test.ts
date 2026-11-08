import {Decimal} from '../lib/decimal';
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
    name: 'ReteIVA 15 %',
    calculation: 'percentage',
    rate: '15.0000',
    kind: 'reteiva',
  },
  {
    id: 'rete25',
    name: 'ReteFuente 2,5 %',
    calculation: 'percentage',
    rate: '2.5000',
    kind: 'retefuente',
  },
  {
    id: 'ninguno',
    name: 'Ninguno',
    calculation: 'percentage',
    rate: '0',
    kind: 'none',
  },
  {
    id: 'ica966',
    name: 'ReteICA 0,966 %',
    calculation: 'percentage',
    rate: '0.9660',
    kind: 'reteica',
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
          withholdings: ['rete4'],
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
          withholdings: ['reteiva15'],
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
          withholdings: ['rete4'],
        }),
      ],
      TAXES,
    );

    expect(totals.lines[0]?.lineTotal).toBe('119000.00');
    expect(totals.lines[0]?.total, 'what the line adds to Total neto').toBe(
      '115000.00',
    );
  });

  it('carries several retenciones on a line, each on its own base (OBS-2)', () => {
    // 1 000 000 with IVA 19 %, ReteFuente 2,5 %, ReteICA 0,966 % (on the base) and ReteIVA 15 % (on the IVA).
    const totals = computeTotals(
      [
        line({
          quantity: '1',
          unit_price: '1000000',
          charge_tax_id: 'iva19',
          withholdings: ['rete25', 'ica966', 'reteiva15'],
        }),
      ],
      TAXES,
    );

    expect(totals.withholdings, 'Retenciones = 25 000 + 9 660 + 28 500').toBe(
      '63160.00',
    );
    expect(totals.net, 'Total neto = 1 000 000 + 190 000 − 63 160').toBe(
      '1126840.00',
    );
    expect(
      totals.lines[0]?.withholdings,
      'each retención keeps its own amount, in the order the line lists them',
    ).toEqual(['25000.00', '9660.00', '28500.00']);
    expect(
      totals.lines[0]?.withholding,
      'the line’s retención is the sum of its retenciones',
    ).toBe('63160.00');
  });

  it('shares the retención cents across every (line, retención) pair', () => {
    // Three lines of 333,33 with ReteFuente 2,5 % and ReteICA 0,966 %: exact 3 × (8.33325 + 3.2199678) = 34.6596534.
    const third = {
      quantity: '1',
      unit_price: '333.33',
      charge_tax_id: 'iva19',
      withholdings: ['rete25', 'ica966'],
    };
    const totals = computeTotals(
      [line(third), line(third), line(third)],
      TAXES,
    );

    expect(totals.withholdings, 'rounded once at document level').toBe('34.66');
    const shares = totals.lines.flatMap((l) => l.withholdings);
    for (const amounts of totals.lines) {
      expect(
        amounts.withholding,
        'a line’s retención is the sum of its shares',
      ).toBe(
        Decimal.sum(amounts.withholdings.map((v) => Decimal.of(v))).toString(),
      );
    }
    expect(
      Decimal.sum(shares.map((v) => Decimal.of(v))).toString(),
      'every share adds up to Retenciones to the cent',
    ).toBe('34.66');
    expect(
      shares,
      'the 4 cents left over go to the largest remainders: ReteICA’s 0.0099678 thrice, then the first ReteFuente',
    ).toEqual(['8.34', '3.22', '8.33', '3.22', '8.33', '3.22']);
  });

  it('gives a line without retenciones no shares', () => {
    const totals = computeTotals(
      [line({quantity: '1', unit_price: '1000', charge_tax_id: 'iva19'})],
      TAXES,
    );

    expect(totals.lines[0]?.withholdings).toEqual([]);
    expect(totals.lines[0]?.withholding).toBe('0.00');
    expect(totals.withholdingsByTax).toEqual([]);
  });

  it('sums the retenciones by tax with their bases, for the totals panel', () => {
    const totals = computeTotals(
      [
        line({
          quantity: '1',
          unit_price: '1000000',
          charge_tax_id: 'iva19',
          withholdings: ['rete25', 'reteiva15'],
        }),
        line({
          quantity: '2',
          unit_price: '100000',
          charge_tax_id: 'iva19',
          withholdings: ['rete25'],
        }),
      ],
      TAXES,
    );

    expect(
      totals.withholdingsByTax,
      'in the order each tax first appears; ReteIVA’s base is the IVA',
    ).toEqual([
      {
        tax_id: 'rete25',
        name: 'ReteFuente 2,5 %',
        base: '1200000.00',
        amount: '30000.00',
      },
      {
        tax_id: 'reteiva15',
        name: 'ReteIVA 15 %',
        base: '190000.00',
        amount: '28500.00',
      },
    ]);
  });

  it('leaves out a retención the company no longer offers', () => {
    const totals = computeTotals(
      [
        line({
          quantity: '1',
          unit_price: '1000',
          withholdings: ['gone', 'rete25'],
        }),
      ],
      TAXES,
    );

    expect(totals.withholdings).toBe('25.00');
    expect(totals.withholdingsByTax.map((w) => w.tax_id)).toEqual(['rete25']);
  });

  it('takes the "Ninguno" retención as none, as the server skips it', () => {
    const totals = computeTotals(
      [line({quantity: '1', unit_price: '1000', withholdings: ['ninguno']})],
      TAXES,
    );

    expect(totals.lines[0]?.withholdings).toEqual([]);
    expect(totals.withholdingsByTax, 'no "Ninguno" row in the panel').toEqual(
      [],
    );
  });
});
