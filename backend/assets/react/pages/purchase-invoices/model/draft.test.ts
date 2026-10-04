import type {DocumentDraft} from '@/widgets/document-editor';
import type {PurchaseInvoice} from '@/entities/purchase-invoice';
import {draftFromInvoice, toRequest} from './draft';

const line = (over: Partial<DocumentDraft['lines'][number]> = {}) => ({
  key: 'l1',
  product: null,
  account: {id: 'acc1', text: '513595 · OTROS'},
  description: 'Mantenimiento',
  quantity: '1',
  unit_price: '1000000',
  discount: '',
  charge_tax_id: 'iva19',
  withholding_tax_id: null,
  ...over,
});

const draft = (over: Partial<DocumentDraft> = {}): DocumentDraft => ({
  type_label: 'FC',
  number: null,
  tercero: {id: 't1', name: 'Servicios Andinos'},
  contact_id: null,
  issue_date: '2026-10-02',
  lines: [line()],
  payments: [
    {
      key: 'p1',
      payment_method_id: 'credit',
      amount: '1190000.00',
      term: '30',
      due_date: '2026-11-01',
    },
  ],
  notes: '  ',
  attachments: [],
  ...over,
});

const invoice = (over: Partial<PurchaseInvoice> = {}): PurchaseInvoice =>
  ({
    id: 'i1',
    status: 'draft',
    number: null,
    tercero_id: 't1',
    tercero_name: 'Servicios Andinos',
    supplier_invoice_number: 'FAC-1',
    issue_date: '2026-10-02',
    due_date: '2026-11-01',
    notes: 'Octubre',
    net_total: '1190000.00',
    paid_amount: '0.00',
    balance: '1190000.00',
    lines: [
      {
        id: 'x1',
        position: 0,
        product_id: 'p1',
        product_label: 'RES-01 · Resma',
        account_id: null,
        account_label: null,
        description: 'Resma',
        quantity: '10.0000',
        unit_price: '15000.0000',
        discount: '0.0000',
        charge_tax_id: null,
        withholding_tax_id: 'rete4',
      },
    ],
    payments: [
      {
        id: 'y1',
        position: 0,
        payment_method_id: 'credit',
        method_name: 'Crédito',
        kind: 'credit',
        amount: '1190000.00',
        due_date: '2026-11-01',
      },
    ],
    attachments: [
      {
        id: 'a1',
        file_name: 'fac.pdf',
        content_type: 'application/pdf',
        size: 10,
        uploaded_at: '',
      },
    ],
    payables: [],
    ...over,
  }) as unknown as PurchaseInvoice;

describe('the purchase invoice draft', () => {
  it('sends the lines typed, leaving out blank ones, and remembers where each came from', () => {
    const blank = line({
      key: 'l2',
      account: null,
      description: '',
      quantity: '',
      unit_price: '',
      charge_tax_id: null,
    });
    const {request, lineIndexes} = toRequest(draft({lines: [blank, line()]}), {
      supplierInvoiceNumber: ' FAC-881 ',
      dueDate: '',
    });

    expect(request.tercero_id).toBe('t1');
    expect(request.supplier_invoice_number).toBe('FAC-881');
    expect(request.due_date).toBeNull();
    expect(request.notes).toBeNull();
    expect(request.lines).toEqual([
      {
        product_id: null,
        account_id: 'acc1',
        description: 'Mantenimiento',
        quantity: '1',
        unit_price: '1000000',
        discount: '0',
        charge_tax_id: 'iva19',
        withholding_tax_id: null,
      },
    ]);
    expect(lineIndexes).toEqual([1]);
    expect(request.payments).toEqual([
      {
        payment_method_id: 'credit',
        amount: '1190000.00',
        due_date: '2026-11-01',
      },
    ]);
  });

  it('opens a saved invoice as the editor shows it', () => {
    const {
      draft: opened,
      supplierInvoiceNumber,
      dueDate,
    } = draftFromInvoice(invoice(), 'FC · Factura de compra');

    expect(supplierInvoiceNumber).toBe('FAC-1');
    expect(dueDate).toBe('2026-11-01');
    expect(opened.tercero).toEqual({id: 't1', name: 'Servicios Andinos'});
    expect(opened.lines[0]).toMatchObject({
      product: {id: 'p1', label: 'RES-01 · Resma'},
      account: null,
      quantity: '10',
      unit_price: '15000',
      discount: '',
      withholding_tax_id: 'rete4',
    });
    expect(opened.payments[0]).toMatchObject({
      payment_method_id: 'credit',
      term: 'custom',
      due_date: '2026-11-01',
    });
    expect(opened.attachments).toEqual([{id: 'a1', name: 'fac.pdf', size: 10}]);
    expect(opened.notes).toBe('Octubre');
  });
});
