import {ApiError} from '@/shared/api';
import {emptyDraft, emptyLine} from '@/widgets/document-editor';
import type {SalesInvoice} from '@/entities/sales-invoice';
import {
  draftFromInvoice,
  editorErrorsFrom,
  requestFromDraft,
  termFor,
} from './draftMapping';

const invoice = (over: Partial<SalesInvoice> = {}): SalesInvoice =>
  ({
    id: 'i1',
    status: 'draft',
    number: null,
    tercero_id: 't1',
    tercero_name: 'Cliente Uno',
    contact_id: null,
    seller_id: null,
    issue_date: '2026-10-01',
    notes: 'Entregar en bodega',
    lines: [
      {
        id: 'l1',
        position: 1,
        product_id: 'p1',
        product_label: 'CONS · Consultoría',
        description: 'Consultoría',
        quantity: '2.0000',
        unit_price: '1000000.0000',
        discount: '0.0000',
        charge_tax_id: 'iva19',
        withholding_tax_id: null,
      },
    ],
    payments: [
      {
        id: 'p1',
        position: 1,
        payment_method_id: 'credito',
        method_name: 'Crédito',
        kind: 'credit',
        amount: '2380000.00',
        due_date: '2026-10-31',
      },
    ],
    ...over,
  }) as unknown as SalesInvoice;

describe('the sales invoice draft and the API', () => {
  it('reads a saved invoice into the form', () => {
    const draft = draftFromInvoice(invoice(), 'Factura (FE)');

    expect(draft.type_label).toBe('Factura (FE)');
    expect(draft.tercero).toEqual({id: 't1', name: 'Cliente Uno'});
    expect(draft.lines[0]).toMatchObject({
      product: {id: 'p1', label: 'CONS · Consultoría'},
      quantity: '2',
      unit_price: '1000000',
      discount: '',
      charge_tax_id: 'iva19',
    });
    expect(draft.payments[0]).toMatchObject({
      payment_method_id: 'credito',
      amount: '2380000.00',
      term: '30',
      due_date: '2026-10-31',
    });
    expect(draft.notes).toBe('Entregar en bodega');
  });

  it('reads the credit term from the due date', () => {
    expect(termFor('2026-10-01', '2026-10-01')).toBe('today');
    expect(termFor('2026-10-01', '2026-10-16')).toBe('15');
    expect(termFor('2026-10-01', '2026-11-30')).toBe('60');
    expect(termFor('2026-10-01', '2026-10-20')).toBe('custom');
  });

  it('sends the draft without its blank lines', () => {
    const draft = {
      ...emptyDraft({typeLabel: 'FE', today: '2026-10-03'}),
      tercero: {id: 't1', name: 'Cliente'},
      notes: '  ',
      lines: [
        emptyLine(),
        {
          ...emptyLine(),
          product: {id: 'p1', label: 'P'},
          description: ' Servicio ',
          quantity: '1',
          unit_price: '100',
        },
      ],
    };

    const {body, lineIndexes} = requestFromDraft(draft, 's1');

    expect(lineIndexes).toEqual([1]);
    expect(body).toMatchObject({
      tercero_id: 't1',
      seller_id: 's1',
      issue_date: '2026-10-03',
      notes: null,
      lines: [
        {
          product_id: 'p1',
          description: 'Servicio',
          quantity: '1',
          unit_price: '100',
          discount: '',
        },
      ],
    });
  });

  it('puts the API violations on the form rows they belong to', () => {
    const error = new ApiError(422, 'validation_failed', '', {
      violations: [
        {field: 'tercero_id', message: 'Elige el cliente.'},
        {field: 'lines[0].quantity', message: 'Cantidad.'},
        {field: 'lines.1.product_id', message: 'Producto.'},
        {field: 'payments.0.due_date', message: 'Vencimiento.'},
      ],
    });

    expect(editorErrorsFrom(error, [2, 4])).toEqual({
      'tercero': 'Elige el cliente.',
      'lines.2.quantity': 'Cantidad.',
      'lines.4.product': 'Producto.',
      'payments.0.due_date': 'Vencimiento.',
    });
    expect(
      editorErrorsFrom(new ApiError(409, 'period_locked', '', null), []),
    ).toBeNull();
  });
});
