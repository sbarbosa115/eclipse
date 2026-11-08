import {translator} from '@/shared/i18n';
import {movedText, usesOf, willMoveText} from './uses';

const t = translator();

const rule = (concept: string, accountId: string) => ({
  concept,
  account_id: accountId,
  account_code: '413595',
  account_name: 'VENTA DE OTROS PRODUCTOS',
  allowed_prefixes: ['41'],
  account_postable: true,
});

describe('what points at an account', () => {
  it('collects the posting rules, both sides of each tax and the payment methods', () => {
    const uses = usesOf(
      [rule('ingreso', 'a1')],
      [{name: 'IVA 19 %', sales_account_id: 'a2', purchase_account_id: 'a3'}],
      [
        {name: 'Efectivo', account_id: 'a4'},
        {name: 'Crédito', account_id: null},
      ],
    );

    expect(uses).toEqual([
      {accountId: 'a1', kind: 'rule', name: 'ingreso'},
      {accountId: 'a2', kind: 'tax', name: 'IVA 19 %'},
      {accountId: 'a3', kind: 'tax', name: 'IVA 19 %'},
      {accountId: 'a4', kind: 'method', name: 'Efectivo'},
    ]);
  });

  it('says what will move to the new auxiliar, products and terceros last', () => {
    const uses = usesOf(
      [rule('ingreso', 'a1')],
      [
        {name: 'IVA 19 %', sales_account_id: 'a1', purchase_account_id: null},
        {name: 'IVA 5 %', sales_account_id: 'a1', purchase_account_id: null},
      ],
      [],
    );

    expect(willMoveText(t, uses)).toBe(
      'regla Ingresos por ventas, 2 impuestos y los productos y terceros que la usen',
    );
    expect(willMoveText(t, [])).toBe('los productos y terceros que la usen');
  });

  it('says what moved, in the words of the answer', () => {
    expect(
      movedText(t, {
        posting_rules: ['ingreso'],
        taxes: 0,
        payment_methods: 1,
        products: 2,
        terceros: 1,
      }),
    ).toBe(
      'regla Ingresos por ventas, 1 forma de pago, 2 productos y 1 tercero',
    );
    expect(
      movedText(t, {
        posting_rules: [],
        taxes: 0,
        payment_methods: 0,
        products: 0,
        terceros: 0,
      }),
    ).toBeNull();
  });
});
