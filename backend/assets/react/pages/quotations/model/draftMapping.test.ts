import {ApiError} from '@/shared/api';
import {emptyDraft, emptyLine} from '@/widgets/document-editor';
import type {Quotation} from '@/entities/quotation';
import {
  draftFromQuotation,
  editorErrorsFrom,
  emptyExtras,
  extrasFromQuotation,
  requestFromDraft,
  validateExtras,
} from './draftMapping';

const quotation = (over: Partial<Quotation> = {}): Quotation =>
  ({
    id: 'q1',
    status: 'draft',
    number: null,
    tercero_id: 't1',
    tercero_name: 'Cliente Uno',
    contact_id: null,
    responsible_id: 'e1',
    responsible_name: 'Elena Pérez',
    issue_date: '2026-10-01',
    expiry_date: '2026-10-31',
    header: 'Estimada Ana',
    terms: '50 % de anticipo',
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
    ...over,
  }) as unknown as Quotation;

describe('the quotation draft and the API', () => {
  it('reads a saved quotation into the form and its extra fields', () => {
    const draft = draftFromQuotation(quotation(), 'Cotización');
    expect(draft.type_label).toBe('Cotización');
    expect(draft.tercero).toEqual({id: 't1', name: 'Cliente Uno'});
    expect(draft.payments).toEqual([]);
    expect(draft.notes).toBe('Entregar en bodega');
    expect(draft.lines[0]).toMatchObject({
      product: {id: 'p1', label: 'CONS · Consultoría'},
      quantity: '2',
      unit_price: '1000000',
      discount: '',
      charge_tax_id: 'iva19',
    });
    expect(extrasFromQuotation(quotation())).toEqual({
      responsible: {id: 'e1', name: 'Elena Pérez'},
      expiry_date: '2026-10-31',
      expiry_touched: true,
      header: 'Estimada Ana',
      terms: '50 % de anticipo',
    });
  });

  it('offers thirty days of validity on a new quotation', () => {
    expect(emptyExtras('2026-10-03').expiry_date).toBe('2026-11-02');
  });

  it('sends the draft without its blank lines and with its own fields', () => {
    const draft = {
      ...emptyDraft({typeLabel: 'Cotización', today: '2026-10-03'}),
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
    const extras = {
      ...emptyExtras('2026-10-03'),
      responsible: {id: 'e1', name: 'Elena'},
      header: '  Hola  ',
      terms: '',
    };

    const {body, lineIndexes} = requestFromDraft(draft, extras);

    expect(lineIndexes).toEqual([1]);
    expect(body).toMatchObject({
      tercero_id: 't1',
      responsible_id: 'e1',
      issue_date: '2026-10-03',
      expiry_date: '2026-11-02',
      header: 'Hola',
      terms: null,
      notes: null,
      lines: [
        {
          product_id: 'p1',
          description: 'Servicio',
          quantity: '1',
          unit_price: '100',
        },
      ],
    });
    expect(body).not.toHaveProperty('payments');
  });

  it('checks the vencimiento', () => {
    const draft = emptyDraft({typeLabel: 'C', today: '2026-10-03'});
    const message = (key: string) => key;

    expect(validateExtras(draft, emptyExtras('2026-10-03'), message)).toEqual(
      {},
    );
    expect(
      validateExtras(
        draft,
        {...emptyExtras('2026-10-03'), expiry_date: ''},
        message,
      ),
    ).toEqual({expiry_date: 'expiry'});
    expect(
      validateExtras(
        draft,
        {...emptyExtras('2026-10-03'), expiry_date: '2026-10-02'},
        message,
      ),
    ).toEqual({expiry_date: 'expiryBeforeIssue'});
  });

  it('puts the API violations on the form rows they belong to', () => {
    const error = new ApiError(422, 'validation_failed', '', {
      violations: [
        {field: 'tercero_id', message: 'Elige el cliente.'},
        {field: 'lines.1.product_id', message: 'Producto.'},
        {field: 'expiry_date', message: 'Vence antes.'},
        {field: 'responsible_id', message: 'Empleado.'},
      ],
    });
    expect(editorErrorsFrom(error, [2, 4])).toEqual({
      'tercero': 'Elige el cliente.',
      'lines.4.product': 'Producto.',
      'expiry_date': 'Vence antes.',
      'responsible_id': 'Empleado.',
    });
    expect(
      editorErrorsFrom(new ApiError(409, 'quotation_not_open', '', null), []),
    ).toBeNull();
  });
});
