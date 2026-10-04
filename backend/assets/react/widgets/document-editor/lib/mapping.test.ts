import {ApiError} from '@/shared/api';
import {emptyLine} from '../model/draft';
import type {DocumentDraft} from '../model/types';
import {trimDecimal} from './decimal';
import {draftLineFrom, editorErrorsFrom, linesToSend} from './mapping';

describe('between the document form and the API', () => {
  it('writes a decimal as a person does', () => {
    expect(trimDecimal('1000000.0000')).toBe('1000000');
    expect(trimDecimal('2.5000')).toBe('2.5');
    expect(trimDecimal('0.0000')).toBe('0');
    expect(trimDecimal('12')).toBe('12');
  });

  it('reads a saved line into a row of the form', () => {
    expect(
      draftLineFrom({
        id: 'l1',
        product_id: 'p1',
        product_label: 'SRV-01 · Consultoría',
        account_id: null,
        description: 'Consultoría',
        quantity: '2.0000',
        unit_price: '1000000.0000',
        discount: '0.0000',
        charge_tax_id: 'iva19',
        withholding_tax_id: null,
      }),
    ).toEqual({
      key: 'line-l1',
      product: {id: 'p1', label: 'SRV-01 · Consultoría'},
      account: null,
      description: 'Consultoría',
      quantity: '2',
      unit_price: '1000000',
      discount: '',
      charge_tax_id: 'iva19',
      withholding_tax_id: null,
    });
    expect(
      draftLineFrom({
        id: 'l2',
        product_id: null,
        account_id: 'a1',
        account_label: '513595 · Otros',
        description: 'Mantenimiento',
        quantity: '1',
        unit_price: '50',
        discount: '10.0000',
      }),
    ).toMatchObject({
      product: null,
      account: {id: 'a1', text: '513595 · Otros'},
      discount: '10',
    });
  });

  it('sends the lines typed, leaving out blank ones, and remembers where each came from', () => {
    const typed = {...emptyLine(), description: 'Uno', quantity: '1'};
    const draft = {
      lines: [emptyLine(), typed, emptyLine()],
    } as unknown as DocumentDraft;

    expect(linesToSend(draft)).toEqual({lines: [typed], lineIndexes: [1]});
  });

  it('puts the API violations on the form rows they belong to', () => {
    const error = new ApiError(422, 'validation_failed', '', {
      violations: [
        {field: 'tercero_id', message: 'Elige el cliente.'},
        {field: 'lines[0].quantity', message: 'Cantidad.'},
        {field: 'lines.1.product_id', message: 'Producto.'},
        {field: 'lines[1].account_id', message: 'Cuenta.'},
        {field: 'payments[1].due_date', message: 'Vencimiento.'},
        {field: 'supplier_invoice_number', message: 'Repetido.'},
      ],
    });

    expect(editorErrorsFrom(error, [2, 4])).toEqual({
      'tercero': 'Elige el cliente.',
      'lines.2.quantity': 'Cantidad.',
      'lines.4.product': 'Producto.',
      'lines.4.account': 'Cuenta.',
      'payments.1.due_date': 'Vencimiento.',
      'supplier_invoice_number': 'Repetido.',
    });
    expect(
      editorErrorsFrom(new ApiError(409, 'period_locked', '', null), []),
      'not a validation error: nothing to put on a field',
    ).toBeNull();
    expect(
      editorErrorsFrom(
        new ApiError(409, 'duplicate_supplier_invoice_number', '', {
          violations: [
            {field: 'supplier_invoice_number', message: 'Repetido.'},
          ],
        }),
        [],
      ),
      'a refusal that names its field goes on it',
    ).toEqual({supplier_invoice_number: 'Repetido.'});
  });
});
