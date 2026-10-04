import {translator} from '@/shared/i18n';
import {
  addLine,
  addPayment,
  applyProduct,
  dueDateFor,
  emptyDraft,
  emptyLine,
  hasErrors,
  moveLine,
  paymentBalance,
  removeLine,
  setIssueDate,
  setLineMode,
  setTercero,
  todayIso,
  updatePayment,
  validateDraft,
} from './draft';
import type {DocumentDraft, DraftLine} from './types';

const t = translator();

const METHODS = [
  {id: 'cash', kind: 'cash'},
  {id: 'credit', kind: 'credit'},
];

const product = {
  id: 'p1',
  code: 'SRV-01',
  name: 'Hora de consultoría',
  description: null,
  unit_price_net_of_tax: '100000.0000',
  charge_tax_id: 'iva19',
  withholding_tax_id: 'rete4',
};

function draftWith(change: Partial<DocumentDraft>): DocumentDraft {
  return {
    ...emptyDraft({typeLabel: 'FV', number: null, today: '2026-10-03'}),
    ...change,
  };
}

function aLine(change: Partial<DraftLine> = {}): DraftLine {
  return {
    ...emptyLine(),
    product: {id: 'p1', label: 'SRV-01 · Hora'},
    quantity: '1',
    unit_price: '100',
    ...change,
  };
}

describe('the document draft', () => {
  it('starts dated today with one empty line and no payments', () => {
    const draft = emptyDraft({
      typeLabel: 'FV · Factura de venta',
      number: null,
      today: '2026-10-03',
    });

    expect(draft.issue_date).toBe('2026-10-03');
    expect(draft.type_label).toBe('FV · Factura de venta');
    expect(draft.lines).toHaveLength(1);
    expect(draft.payments).toEqual([]);
  });

  it('formats today as the API’s date', () => {
    expect(todayIso(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05');
  });

  it('forgets the contact when the tercero changes: it belonged to the other one', () => {
    const draft = draftWith({tercero: {id: 'a', name: 'A'}, contact_id: 'c1'});

    const next = setTercero(draft, {id: 'b', name: 'B'});

    expect(next.tercero).toEqual({id: 'b', name: 'B'});
    expect(next.contact_id).toBeNull();
  });

  it('adds, removes and reorders lines, keeping at least one', () => {
    let draft = draftWith({lines: [aLine({description: 'uno'})]});
    draft = addLine(draft);
    draft = {
      ...draft,
      lines: draft.lines.map((l, i) =>
        i === 1 ? {...l, description: 'dos'} : l,
      ),
    };

    const moved = moveLine(draft, 1, 0);
    expect(moved.lines.map((l) => l.description)).toEqual(['dos', 'uno']);
    expect(
      moveLine(draft, 0, -1).lines,
      'moving past the edge changes nothing',
    ).toBe(draft.lines);

    const removed = removeLine(removeLine(draft, 0), 0);
    expect(removed.lines, 'the last line is emptied, not removed').toHaveLength(
      1,
    );
    expect(removed.lines[0]?.description).toBe('');
  });

  it('fills a line from the product chosen: name, price net of IVA, taxes', () => {
    const filled = applyProduct('sales_invoice', emptyLine(), product);

    expect(filled.product).toEqual({
      id: 'p1',
      label: 'SRV-01 · Hora de consultoría',
    });
    expect(filled.description).toBe('Hora de consultoría');
    expect(filled.unit_price, 'unit_price_net_of_tax, trailing zeros off').toBe(
      '100000',
    );
    expect(filled.quantity).toBe('1');
    expect(filled.charge_tax_id).toBe('iva19');
    expect(filled.withholding_tax_id).toBe('rete4');
  });

  it('keeps the price on a purchase line: the product’s is a sale price', () => {
    const filled = applyProduct(
      'purchase_invoice',
      {...emptyLine(), unit_price: '80000'},
      product,
    );

    expect(filled.unit_price).toBe('80000');
    expect(filled.product?.id).toBe('p1');
  });

  it('switches a purchase line between a product and an expense account', () => {
    const byAccount = setLineMode(aLine(), 'account');
    expect(byAccount.product).toBeNull();
    expect(byAccount.account).toEqual({id: null, text: ''});

    const byProduct = setLineMode(byAccount, 'product');
    expect(byProduct.account).toBeNull();
  });

  it('computes a credit due date from the issue date', () => {
    expect(dueDateFor('today', '2026-10-03', null)).toBe('2026-10-03');
    expect(dueDateFor('15', '2026-10-03', null)).toBe('2026-10-18');
    expect(dueDateFor('30', '2026-10-03', null)).toBe('2026-11-02');
    expect(dueDateFor('60', '2026-12-15', null)).toBe('2027-02-13');
    expect(dueDateFor('custom', '2026-10-03', '2026-12-24')).toBe('2026-12-24');
  });

  it('moves the due dates with the issue date, except a date typed by hand', () => {
    const draft = draftWith({
      payments: [
        {
          key: 'a',
          payment_method_id: 'credit',
          amount: '10',
          term: '30',
          due_date: '2026-11-02',
        },
        {
          key: 'b',
          payment_method_id: 'credit',
          amount: '10',
          term: 'custom',
          due_date: '2026-12-24',
        },
      ],
    });

    const next = setIssueDate(draft, '2026-10-10');

    expect(next.payments.map((p) => p.due_date)).toEqual([
      '2026-11-09',
      '2026-12-24',
    ]);
  });

  it('keeps a crédito row’s due date while the issue date is being typed, and moves it once it is a date', () => {
    const draft = draftWith({
      payments: [
        {
          key: 'a',
          payment_method_id: 'credit',
          amount: '10',
          term: '30',
          due_date: '2026-11-02',
        },
      ],
    });

    const typing = setIssueDate(draft, '');
    expect(
      typing.payments[0]?.due_date,
      'a half-typed date (DateInput hands back "") must not turn the row into a non-crédito one',
    ).toBe('2026-11-02');

    const typed = setIssueDate(typing, '2026-10-10');
    expect(typed.payments[0]?.due_date).toBe('2026-11-09');
  });

  it('offers what is left of Total neto when a payment row is added', () => {
    let draft = addPayment(draftWith({}), '1190.00');
    expect(draft.payments[0]?.amount).toBe('1190.00');

    draft = updatePayment(draft, 0, {amount: '1000'}, METHODS);
    draft = addPayment(draft, '1190.00');
    expect(draft.payments[1]?.amount).toBe('190.00');

    draft = addPayment(draft, '1190.00');
    expect(draft.payments[2]?.amount, 'nothing left: empty').toBe('');
  });

  it('gives a credit payment a due date and a cash one none', () => {
    let draft = addPayment(draftWith({}), '100.00');
    draft = updatePayment(draft, 0, {payment_method_id: 'credit'}, METHODS);
    expect(draft.payments[0]?.term).toBe('30');
    expect(draft.payments[0]?.due_date).toBe('2026-11-02');

    draft = updatePayment(draft, 0, {term: '15'}, METHODS);
    expect(draft.payments[0]?.due_date).toBe('2026-10-18');

    draft = updatePayment(draft, 0, {payment_method_id: 'cash'}, METHODS);
    expect(draft.payments[0]?.due_date).toBeNull();
  });

  it('says when the payments match Total neto, and by how much they do not', () => {
    const pay = (amount: string) => ({
      key: amount,
      payment_method_id: 'cash',
      amount,
      term: '30' as const,
      due_date: null,
    });

    expect(paymentBalance([pay('595'), pay('595.00')], '1190.00')).toEqual({
      total: '1190.00',
      matches: true,
      difference: '0.00',
    });
    expect(paymentBalance([pay('1000')], '1190.00')).toEqual({
      total: '1000.00',
      matches: false,
      difference: '-190.00',
    });
    expect(
      paymentBalance([], '0.00').matches,
      'no payments on an empty document is not "they match"',
    ).toBe(false);
  });
});

describe('checking the draft before it is saved', () => {
  const complete = draftWith({
    tercero: {id: 'c', name: 'Cliente'},
    lines: [aLine()],
    payments: [
      {
        key: 'p',
        payment_method_id: 'cash',
        amount: '100.00',
        term: '30',
        due_date: null,
      },
    ],
  });

  it('accepts a complete draft', () => {
    expect(
      validateDraft('sales_invoice', complete, {
        net: '100.00',
        methods: METHODS,
        forEmission: true,
        t,
      }),
    ).toEqual({});
  });

  it('names every missing or wrong field by its path', () => {
    const errors = validateDraft(
      'sales_invoice',
      draftWith({
        issue_date: '',
        lines: [
          aLine({product: null, quantity: '0', unit_price: '1,5'}),
          aLine({discount: '120', quantity: '1.23456'}),
        ],
        payments: [
          {
            key: 'p',
            payment_method_id: null,
            amount: '',
            term: 'custom',
            due_date: null,
          },
        ],
      }),
      {net: '100.00', methods: METHODS, forEmission: false, t},
    );

    expect(errors).toEqual({
      'tercero': 'Elige el tercero.',
      'issue_date': 'Escribe la fecha de elaboración.',
      'lines.0.product': 'Elige un producto o servicio.',
      'lines.0.quantity': 'La cantidad debe ser mayor que cero.',
      'lines.0.unit_price':
        'Escribe un valor en pesos, con máximo cuatro decimales y punto decimal.',
      'lines.1.quantity':
        'Escribe un número con máximo cuatro decimales y punto decimal.',
      'lines.1.discount': 'El descuento es un porcentaje entre 0 y 100.',
      'payments.0.payment_method_id': 'Elige el método de pago.',
      'payments.0.amount':
        'Escribe un valor mayor que cero, con máximo dos decimales.',
    });
    expect(hasErrors(errors)).toBe(true);
  });

  it('asks a purchase line for a product or an account', () => {
    const errors = validateDraft(
      'purchase_invoice',
      draftWith({
        tercero: {id: 'p', name: 'Proveedor'},
        lines: [aLine({product: null}), setLineMode(aLine(), 'account')],
      }),
      {net: '0.00', methods: METHODS, forEmission: false, t},
    );

    expect(errors['lines.0.product']).toBe(
      'Elige un producto o servicio, o una cuenta de gasto.',
    );
    expect(errors['lines.1.account']).toBe('Elige una cuenta de la lista.');
  });

  it('asks for a due date on a credit payment, not before the issue date', () => {
    const errors = validateDraft(
      'sales_invoice',
      {
        ...complete,
        payments: [
          {
            key: 'p',
            payment_method_id: 'credit',
            amount: '100',
            term: 'custom',
            due_date: '2026-10-01',
          },
        ],
      },
      {net: '100.00', methods: METHODS, forEmission: false, t},
    );

    expect(errors['payments.0.due_date']).toBe(
      'El vencimiento no puede ser anterior a la fecha de elaboración.',
    );
  });

  it('refuses to emit while the payments do not add up to Total neto, but lets a draft be saved', () => {
    const options = {net: '119.00', methods: METHODS, t};

    expect(
      validateDraft('sales_invoice', complete, {...options, forEmission: true})
        .payments,
    ).toBe(
      'Total formas de pago ($ 100,00) debe ser igual al total neto ($ 119,00).',
    );
    expect(
      validateDraft('sales_invoice', complete, {
        ...options,
        forEmission: false,
      }),
    ).toEqual({});
  });

  it('ignores payments on a quotation: it has none', () => {
    expect(
      validateDraft('quotation', complete, {
        net: '999.00',
        methods: METHODS,
        forEmission: true,
        t,
      }),
    ).toEqual({});
  });
});
