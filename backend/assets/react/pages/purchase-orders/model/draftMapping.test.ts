import type {PurchaseOrder} from '@/entities/purchase-order';
import {emptyDraft, emptyLine} from '@/widgets/document-editor';
import {
  draftFromOrder,
  emptyExtras,
  extrasFromOrder,
  requestFromDraft,
  validateExtras,
} from './draftMapping';

const order = (over: Partial<PurchaseOrder> = {}): PurchaseOrder =>
  ({
    id: 'o1',
    status: 'emitted',
    number: 'OC-3',
    tercero_id: 't1',
    tercero_name: 'Papelería Andina S.A.S.',
    issue_date: '2026-11-02',
    expected_date: '2026-11-20',
    notes: 'Entregar en bodega',
    lines: [
      {
        id: 'l1',
        position: 1,
        product_id: 'p1',
        product_label: 'RES-01 · Resma carta',
        product_code: 'RES-01',
        account_id: null,
        description: 'Resmas',
        quantity: '10.0000',
        unit_price: '20000.0000',
        discount: '0.0000',
        charge_tax_id: 'iva19',
        withholdings: [{tax_id: 'rete25', name: 'ReteFuente 2,5 %'}],
      },
      {
        id: 'l2',
        position: 2,
        product_id: null,
        account_id: 'a1',
        account_label: '513595 · Otros',
        description: 'Mantenimiento',
        quantity: '1.0000',
        unit_price: '500000.0000',
        discount: '5.0000',
        charge_tax_id: null,
        withholdings: [],
      },
    ],
    ...over,
  }) as unknown as PurchaseOrder;

describe('the purchase order and the document form', () => {
  it('reads a saved order into the form without formas de pago', () => {
    const draft = draftFromOrder(order(), 'Orden de compra');

    expect(draft.number).toBe('OC-3');
    expect(draft.tercero).toEqual({
      id: 't1',
      name: 'Papelería Andina S.A.S.',
    });
    expect(draft.payments).toEqual([]);
    expect(draft.lines).toHaveLength(2);
    expect(draft.lines[0]).toMatchObject({
      quantity: '10',
      unit_price: '20000',
      withholdings: ['rete25'],
      product: {id: 'p1', code: 'RES-01'},
    });
    expect(draft.lines[1]).toMatchObject({
      account: {id: 'a1'},
      product: null,
      discount: '5',
    });
    expect(extrasFromOrder(order())).toEqual({expected_date: '2026-11-20'});
    expect(extrasFromOrder(order({expected_date: null}))).toEqual({
      expected_date: '',
    });
  });

  it('sends the lines by product or by account, every retención, and no blank line', () => {
    const draft = draftFromOrder(order(), 'Orden de compra');
    draft.lines.push(emptyLine());

    const {body, lineIndexes} = requestFromDraft(draft, {
      expected_date: '2026-11-20',
    });

    expect(lineIndexes).toEqual([0, 1]);
    expect(body).toMatchObject({
      tercero_id: 't1',
      issue_date: '2026-11-02',
      expected_date: '2026-11-20',
      notes: 'Entregar en bodega',
    });
    expect(body.lines[0]).toMatchObject({
      product_id: 'p1',
      account_id: null,
      withholdings: [{tax_id: 'rete25'}],
    });
    expect(body.lines[1]).toMatchObject({
      product_id: null,
      account_id: 'a1',
      discount: '5',
    });
    expect(body).not.toHaveProperty('payments');
  });

  it('sends no expected date when none was chosen', () => {
    const draft = emptyDraft({typeLabel: 'Orden de compra'});

    expect(
      requestFromDraft(draft, emptyExtras()).body.expected_date,
    ).toBeNull();
  });

  it('checks the expected date against the order date', () => {
    const draft = draftFromOrder(order(), 'Orden de compra');
    const message = (key: string) => key;

    expect(validateExtras(draft, {expected_date: ''}, message)).toEqual({});
    expect(
      validateExtras(draft, {expected_date: '2026-11-02'}, message),
    ).toEqual({});
    expect(
      validateExtras(draft, {expected_date: '2026-11-01'}, message),
    ).toEqual({expected_date: 'expectedBeforeIssue'});
    expect(validateExtras(draft, {expected_date: '2026-1'}, message)).toEqual({
      expected_date: 'expected',
    });
  });
});
