import {translator} from '@/shared/i18n';
import {
  applyProduct,
  emptyDraft,
  emptyLine,
  hasPayments,
  isPurchase,
  setLineMode,
  validateDraft,
} from './draft';

// The purchase order (observations PRD §4.8) mounts the shared form as its own kind: a purchase invoice's lines, no
// formas de pago.
describe('the purchase_order kind of the document form', () => {
  const t = translator();

  it('is a purchase without formas de pago', () => {
    expect([
      isPurchase('purchase_order'),
      hasPayments('purchase_order'),
    ]).toEqual([true, false]);
    expect([
      isPurchase('purchase_invoice'),
      hasPayments('purchase_invoice'),
    ]).toEqual([true, true]);
    expect([isPurchase('quotation'), hasPayments('quotation')]).toEqual([
      false,
      false,
    ]);
    expect([isPurchase('sales_invoice'), hasPayments('sales_invoice')]).toEqual(
      [false, true],
    );
  });

  it('asks a line for a product or an account, and ignores formas de pago', () => {
    const draft = {
      ...emptyDraft({typeLabel: 'Orden de compra'}),
      tercero: {id: 't1', name: 'Proveedor'},
      lines: [
        {...emptyLine(), description: 'Algo', quantity: '1', unit_price: '10'},
      ],
      // A stray payment would be an error on an invoice; an order never reads it.
      payments: [
        {
          key: 'p',
          payment_method_id: null,
          amount: '',
          term: 'today' as const,
          due_date: null,
        },
      ],
    };

    const errors = validateDraft('purchase_order', draft, {
      net: '10.00',
      methods: [],
      forEmission: true,
      t,
    });

    expect(errors['lines.0.product']).toBe(
      'Elige un producto o servicio, o una cuenta de gasto.',
    );
    expect(Object.keys(errors).some((key) => key.startsWith('payments'))).toBe(
      false,
    );
  });

  it('keeps the price typed when a product is chosen, as a purchase does', () => {
    const line = applyProduct(
      'purchase_order',
      {...emptyLine(), unit_price: '15000'},
      {
        id: 'p1',
        code: 'RES-01',
        name: 'Resma',
        unit_price_net_of_tax: '20000',
        charge_tax_id: null,
        withholding_tax_id: null,
      },
    );

    expect(line.unit_price).toBe('15000');
    expect(setLineMode(line, 'account').account).toEqual({id: null, text: ''});
  });
});
