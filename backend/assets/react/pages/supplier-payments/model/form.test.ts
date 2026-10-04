import type {OpenPayable} from '../api/supplierPaymentApi';
import {
  emptyForm,
  fieldErrorsFrom,
  openItemsOf,
  requestFrom,
  validateForm,
  type PaymentForm,
} from './form';

const t = (key: string) => key;

const PAYABLES: OpenPayable[] = [
  {
    id: 'r1',
    invoice_id: 'i1',
    invoice_number: 'FC-1',
    issue_date: '2026-09-01',
    due_date: '2026-10-01',
    amount: '595000.00',
    balance: '595000.00',
  },
  {
    id: 'r2',
    invoice_id: 'i2',
    invoice_number: 'FC-2',
    issue_date: '2026-09-02',
    due_date: '2026-10-02',
    amount: '300000.00',
    balance: '300000.00',
  },
];

const filled = (over: Partial<PaymentForm> = {}): PaymentForm => ({
  ...emptyForm('2026-10-03'),
  supplier: {id: 't1', name: 'Proveedor Uno S.A.S.'},
  methodId: 'cash',
  amount: '695.000',
  amounts: {r1: '595000', r2: '100000'},
  ...over,
});

describe('the new payment form', () => {
  it('starts dated today, with nothing chosen', () => {
    expect(emptyForm('2026-10-03')).toEqual({
      supplier: null,
      date: '2026-10-03',
      methodId: '',
      amount: '',
      notes: '',
      amounts: {},
    });
  });

  it('names what is missing before anything is sent', () => {
    expect(validateForm({...emptyForm(''), amount: 'x'}, t)).toEqual({
      tercero_id: 'supplierPayment.form.required.supplier',
      receipt_date: 'supplierPayment.form.required.date',
      payment_method_id: 'supplierPayment.form.required.method',
      amount: 'supplierPayment.form.required.amount',
    });
    expect(validateForm(filled(), t)).toEqual({});
  });

  it('sends decimal strings and only the rows with an amount', () => {
    expect(
      requestFrom(
        filled({notes: '  Transferencia ', amounts: {r1: '695.000'}}),
        PAYABLES,
        true,
      ),
    ).toEqual({
      tercero_id: 't1',
      receipt_date: '2026-10-03',
      payment_method_id: 'cash',
      amount: '695000.00',
      notes: 'Transferencia',
      allocations: [{payable_id: 'r1', amount: '695000.00'}],
      send: true,
    });
  });

  it('shows the payables as open items', () => {
    expect(openItemsOf(PAYABLES)[1]).toEqual({
      id: 'r2',
      document: 'FC-2',
      issueDate: '2026-09-02',
      dueDate: '2026-10-02',
      amount: '300000.00',
      balance: '300000.00',
    });
  });

  it('puts the API’s violations on the fields, an allocation’s on its row', () => {
    const request = requestFrom(filled(), PAYABLES, false);
    expect(
      fieldErrorsFrom(
        [
          {field: 'receipt_date', message: 'No futura.'},
          {field: 'allocations.1.payable_id', message: 'Otra.'},
          {field: 'allocations[0].amount', message: 'Valor.'},
        ],
        request,
      ),
    ).toEqual({
      fields: {receipt_date: 'No futura.'},
      rows: {r2: 'Otra.', r1: 'Valor.'},
    });
  });
});
