import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {PurchaseInvoicesPage} from './PurchaseInvoicesPage';

const session = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role,
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
});

const TAXES = [
  {
    id: 'iva19',
    name: 'IVA 19 %',
    tax_class: 'charge',
    kind: 'iva',
    calculation: 'percentage',
    rate: '19.0000',
    active: true,
    standard: true,
  },
  {
    id: 'rete4',
    name: 'ReteFuente servicios 4 %',
    tax_class: 'withholding',
    kind: 'retefuente',
    calculation: 'percentage',
    rate: '4.0000',
    active: true,
    standard: true,
  },
];
const METHODS = [
  {id: 'cash', name: 'Efectivo', kind: 'cash', active: true, standard: true},
  {id: 'credit', name: 'Crédito', kind: 'credit', active: true, standard: true},
];

const invoice = (over: Record<string, unknown> = {}) => ({
  id: 'i1',
  status: 'draft',
  number: null,
  tercero_id: 't1',
  tercero_name: 'Servicios Andinos S.A.S.',
  supplier_invoice_number: 'FAC-881',
  issue_date: '2026-10-02',
  due_date: '2026-11-01',
  notes: null,
  gross_total: '1000000.00',
  discount_total: '0.00',
  subtotal: '1000000.00',
  tax_total: '190000.00',
  withholding_total: '40000.00',
  net_total: '1150000.00',
  paid_amount: '0.00',
  balance: '1150000.00',
  lines: [
    {
      id: 'x1',
      position: 0,
      product_id: null,
      product_label: null,
      account_id: 'acc1',
      account_label: '513595 · OTROS',
      description: 'Mantenimiento de equipos',
      quantity: '1.0000',
      unit_price: '1000000.0000',
      discount: '0.0000',
      charge_tax_id: 'iva19',
      charge_tax_name: 'IVA 19 %',
      withholding_tax_id: 'rete4',
      withholding_tax_name: 'ReteFuente servicios 4 %',
    },
  ],
  payments: [
    {
      id: 'y1',
      position: 0,
      payment_method_id: 'credit',
      method_name: 'Crédito',
      kind: 'credit',
      amount: '1150000.00',
      due_date: '2026-11-01',
    },
  ],
  payables: [],
  attachments: [
    {
      id: 'a1',
      file_name: 'factura-881.pdf',
      content_type: 'application/pdf',
      size: 1200,
      uploaded_at: '',
    },
  ],
  journal_entry_id: null,
  reversal_entry_id: null,
  created_at: '2026-10-02T10:00:00+00:00',
  emitted_at: null,
  voided_at: null,
  void_reason: null,
  ...over,
});

const OPTIONS = {
  'GET /taxes': [200, {items: TAXES}] as [number, unknown],
  'GET /payment-methods': [200, {items: METHODS}] as [number, unknown],
  'GET /terceros/t1/contacts': [200, {items: []}] as [number, unknown],
};

function Where() {
  const location = useLocation();
  return <p data-testid="where">{location.pathname}</p>;
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/facturas-compra${path}`]}>
      <SessionProvider>
        <Routes>
          <Route
            path="facturas-compra/*"
            element={
              <>
                <PurchaseInvoicesPage />
                <Where />
              </>
            }
          />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

describe('the purchase invoice pages', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists invoices under a header with the primary action', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices': [
        200,
        {items: [], total: 0, page: 1, per_page: 25},
      ],
    });
    renderAt('');

    expect(
      await screen.findByRole('heading', {name: 'Facturas de compra'}),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole('link', {name: 'Nueva factura de compra'}),
    ).toHaveAttribute('href', '/facturas-compra/nueva');
  });

  it('opens a draft with its supplier number, due date, lines and files', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices/i1': [200, invoice()],
      ...OPTIONS,
    });
    renderAt('/i1');

    expect(
      await screen.findByRole('heading', {
        name: 'Factura de compra (borrador)',
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText('Número de factura del proveedor'),
    ).toHaveValue('FAC-881');
    expect(screen.getByLabelText(/Fecha de vencimiento/)).toHaveValue(
      '01/11/2026',
    );
    expect(
      screen.getByDisplayValue('Mantenimiento de equipos'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Descargar factura-881.pdf'}),
    ).toHaveAttribute('href', '/api/v1/purchase-invoices/i1/attachments/a1');
    expect(screen.getByRole('button', {name: 'Emitir'})).toBeEnabled();
  });

  it('saves the draft with the supplier number typed', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices/i1': [200, invoice()],
      'PUT /purchase-invoices/i1': (body) => [
        200,
        invoice({
          supplier_invoice_number: (body as {supplier_invoice_number: string})
            .supplier_invoice_number,
        }),
      ],
      ...OPTIONS,
    });
    renderAt('/i1');
    const number = await screen.findByLabelText(
      'Número de factura del proveedor',
    );
    await userEvent.clear(number);
    await userEvent.type(number, 'FAC-900');
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar borrador'}),
    );

    expect(await screen.findByText('Borrador guardado.')).toBeInTheDocument();
    const put = api.calls.find((c) => c.method === 'PUT');
    expect(put?.body).toMatchObject({
      tercero_id: 't1',
      supplier_invoice_number: 'FAC-900',
      due_date: '2026-11-01',
      lines: [
        {
          account_id: 'acc1',
          product_id: null,
          quantity: '1',
          unit_price: '1000000',
          charge_tax_id: 'iva19',
        },
      ],
      payments: [
        {
          payment_method_id: 'credit',
          amount: '1150000.00',
          due_date: '2026-11-01',
        },
      ],
    });
  });

  it('emits: saves, then emits, and shows the number', async () => {
    const api = fakeApi({
      'GET /me': [200, session('billing')],
      'GET /purchase-invoices/i1': [200, invoice()],
      'PUT /purchase-invoices/i1': [200, invoice()],
      'POST /purchase-invoices/i1/emit': [
        200,
        invoice({status: 'emitted', number: 'FC-1'}),
      ],
      ...OPTIONS,
    });
    renderAt('/i1');
    await userEvent.click(await screen.findByRole('button', {name: 'Emitir'}));

    expect(
      await screen.findByText('Factura FC-1 emitida y contabilizada.'),
    ).toBeInTheDocument();
    expect(api.calls.map((c) => `${c.method} ${c.path}`)).toEqual(
      expect.arrayContaining([
        'PUT /purchase-invoices/i1',
        'POST /purchase-invoices/i1/emit',
      ]),
    );
    expect(
      screen.getByRole('heading', {name: 'Factura de compra FC-1'}),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Emitir'}),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Anular'})).toBeInTheDocument();
  });

  it("shows the API's refusal next to the supplier number", async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices/i1': [200, invoice()],
      'PUT /purchase-invoices/i1': [
        422,
        {
          error: 'duplicate_supplier_invoice_number',
          message: 'x',
          violations: [
            {
              field: 'supplier_invoice_number',
              message:
                'Ya registraste una factura de este proveedor con este número.',
            },
          ],
        },
      ],
      ...OPTIONS,
    });
    renderAt('/i1');
    await userEvent.click(
      await screen.findByRole('button', {name: 'Guardar borrador'}),
    );

    const field = screen.getByLabelText('Número de factura del proveedor');
    await waitFor(() => expect(field).toHaveAttribute('aria-invalid', 'true'));
    expect(
      screen.getAllByText(
        'Ya registraste una factura de este proveedor con este número.',
      ).length,
    ).toBeGreaterThan(0);
  });

  it('checks a new draft before sending it', async () => {
    const api = fakeApi({'GET /me': [200, session('owner')], ...OPTIONS});
    renderAt('/nueva');
    await userEvent.click(
      await screen.findByRole('button', {name: 'Guardar borrador'}),
    );

    expect(
      await screen.findByText('Revisa los campos marcados.'),
    ).toBeInTheDocument();
    expect(api.calls.some((c) => c.method === 'POST')).toBe(false);
  });

  it('shows an emitted invoice read-only, and voids it after asking why', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices/i1': [
        200,
        invoice({status: 'emitted', number: 'FC-1'}),
      ],
      'POST /purchase-invoices/i1/void': [
        200,
        invoice({status: 'voided', number: 'FC-1', void_reason: 'Duplicada'}),
      ],
      ...OPTIONS,
    });
    renderAt('/i1');

    expect(
      await screen.findByText(/ya fue emitida: no se puede modificar/),
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText('Número de factura del proveedor'),
    ).toBeDisabled();
    expect(screen.getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/purchase-invoices/i1/pdf',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Anular'}));
    const dialog = screen.getByRole('dialog');
    await userEvent.type(
      within(dialog).getByLabelText('Motivo de la anulación'),
      'Duplicada',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular factura'}),
    );

    expect(
      await screen.findByText('Factura FC-1 anulada.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Anulada: Duplicada')).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
  });

  it('duplicates an emitted invoice into a new draft', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /purchase-invoices/i1': [
        200,
        invoice({status: 'emitted', number: 'FC-1'}),
      ],
      'POST /purchase-invoices/i1/duplicate': [
        201,
        invoice({id: 'i2', supplier_invoice_number: null}),
      ],
      'GET /purchase-invoices/i2': [
        200,
        invoice({id: 'i2', supplier_invoice_number: null}),
      ],
      ...OPTIONS,
    });
    renderAt('/i1');
    await userEvent.click(
      await screen.findByRole('button', {name: 'Duplicar'}),
    );

    expect(
      await screen.findByText(/Se creó un borrador igual/),
    ).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent(
      '/facturas-compra/i2',
    );
    expect(
      await screen.findByLabelText('Número de factura del proveedor'),
    ).toHaveValue('');
  });

  it('lets the accountant read a draft but not change it', async () => {
    fakeApi({
      'GET /me': [200, session('accountant')],
      'GET /purchase-invoices/i1': [200, invoice()],
      ...OPTIONS,
    });
    renderAt('/i1');

    expect(
      await screen.findByLabelText('Número de factura del proveedor'),
    ).toBeDisabled();
    expect(
      screen.queryByRole('button', {name: 'Emitir'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar borrador'}),
    ).not.toBeInTheDocument();
  });
});
