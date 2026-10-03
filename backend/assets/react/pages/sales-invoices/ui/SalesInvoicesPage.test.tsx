import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {SalesInvoicesPage} from './SalesInvoicesPage';

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

const tax = (id: string, name: string, taxClass: string, rate: string) => ({
  id,
  name,
  tax_class: taxClass,
  kind: taxClass === 'charge' ? 'iva' : 'retefuente',
  calculation: 'percentage',
  rate,
  active: true,
  standard: true,
});
const CHARGE = [tax('iva19', 'IVA 19 %', 'charge', '19.0000')];
const WITHHOLDING = [
  tax('rete4', 'ReteFuente servicios 4 %', 'withholding', '4.0000'),
];
const METHODS = [
  {id: 'cash', name: 'Efectivo', kind: 'cash', active: true, standard: true},
  {id: 'credit', name: 'Crédito', kind: 'credit', active: true, standard: true},
];

const resolution = (status: Record<string, unknown> = {}) => ({
  resolution: {prefix: 'FE'},
  status: {
    status: 'active',
    numbers_left: 900,
    days_left: 200,
    warning: false,
    warning_numbers: 100,
    warning_days: 30,
    ...status,
  },
  manual_invoicing_confirmed_at: null,
});

const summary = (over: Record<string, unknown> = {}) => ({
  id: 'i1',
  status: 'emitted',
  number: 'FE-7',
  issue_date: '2026-10-01',
  due_date: '2026-10-31',
  tercero_id: 't1',
  tercero_name: 'Cliente Uno S.A.S.',
  subtotal: '1000000.00',
  tax_total: '190000.00',
  withholding_total: '0.00',
  net_total: '1190000.00',
  paid_amount: '0.00',
  balance: '1190000.00',
  ...over,
});

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

const invoice = (over: Record<string, unknown> = {}) => ({
  ...summary(),
  prefix: 'FE',
  authorised_number: 7,
  internal_number: 7,
  resolution_id: 'r1',
  contact_id: null,
  seller_id: null,
  quotation_id: null,
  notes: null,
  gross_total: '1000000.00',
  discount_total: '0.00',
  lines: [
    {
      id: 'l1',
      position: 1,
      product_id: 'p1',
      product_label: 'CONS · Consultoría',
      description: 'Consultoría',
      quantity: '1.0000',
      unit_price: '1000000.0000',
      discount: '0.0000',
      charge_tax_id: 'iva19',
      charge_tax_name: 'IVA 19 %',
      charge_tax_rate: '19.0000',
      charge_tax_calculation: 'percentage',
      withholding_tax_id: null,
      withholding_tax_name: 'Ninguno',
      withholding_tax_rate: '0.0000',
    },
  ],
  payments: [
    {
      id: 'p1',
      position: 1,
      payment_method_id: 'credit',
      method_name: 'Crédito',
      kind: 'credit',
      amount: '1190000.00',
      due_date: '2026-10-31',
    },
  ],
  receivables: [],
  journal_entry_id: 'e1',
  reversal_entry_id: null,
  created_by: 'u1',
  created_at: '2026-10-01T10:00:00+00:00',
  emitted_by: 'u1',
  emitted_at: '2026-10-01T10:00:00+00:00',
  voided_by: null,
  voided_at: null,
  void_reason: null,
  ...over,
});

function api(role: string, extra: Parameters<typeof fakeApi>[0] = {}) {
  return fakeApi({
    'GET /me': [200, session(role)],
    'GET /taxes': (_body, url) => [
      200,
      {
        items:
          url.searchParams.get('class') === 'charge' ? CHARGE : WITHHOLDING,
      },
    ],
    'GET /payment-methods': [200, {items: METHODS}],
    'GET /company/resolution': [200, resolution()],
    ...extra,
  });
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/facturas-venta${path}`]}>
      <SessionProvider>
        <Routes>
          <Route path="facturas-venta/*" element={<SalesInvoicesPage />} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

describe('the sales invoices list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('says what the section is for when there is nothing yet', async () => {
    api('owner', {'GET /sales-invoices': [200, page([])]});
    renderAt('');

    expect(
      await screen.findByText(/Aún no has hecho facturas de venta/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Crear la primera factura'}),
    ).toHaveAttribute('href', '/facturas-venta/nueva');
  });

  it('shows a row with its number, client, totals and actions', async () => {
    api('owner', {'GET /sales-invoices': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).getByRole('link', {name: 'FE-7'})).toBeInTheDocument();
    expect(row).toHaveTextContent('Emitida');
    expect(row).toHaveTextContent('01/10/2026');
    expect(row).toHaveTextContent('1.190.000');
    expect(within(row).getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/sales-invoices/i1/pdf',
    );
    for (const name of ['Duplicar', 'Enviar', 'Anular']) {
      expect(within(row).getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('offers no void once money was collected, and nothing to write to the accountant', async () => {
    api('accountant', {
      'GET /sales-invoices': [
        200,
        page([summary({status: 'partially_paid', paid_amount: '10.00'})]),
      ],
    });
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).queryByRole('button')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Nueva factura'}),
    ).not.toBeInTheDocument();
  });

  it('filters by status and offers a way back when nothing matches', async () => {
    const {calls} = api('owner', {
      'GET /sales-invoices': (_body, url) => [
        200,
        page(url.searchParams.get('status') === 'voided' ? [] : [summary()]),
      ],
    });
    renderAt('');
    await screen.findByText('Cliente Uno S.A.S.');

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'voided');

    expect(
      await screen.findByText('Ninguna factura coincide con estos filtros.'),
    ).toBeInTheDocument();
    expect(calls.at(-1)?.url.searchParams.get('status')).toBe('voided');
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByText('Cliente Uno S.A.S.')).toBeInTheDocument();
  });

  it('voids a row with a reason', async () => {
    const {calls} = api('owner', {
      'GET /sales-invoices': [200, page([summary()])],
      'POST /sales-invoices/i1/void': [200, invoice({status: 'voided'})],
    });
    renderAt('');
    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;

    await userEvent.click(within(row).getByRole('button', {name: 'Anular'}));
    const dialog = screen.getByRole('dialog', {name: 'Anular la factura FE-7'});
    await userEvent.type(
      within(dialog).getByLabelText('Motivo de la anulación'),
      'Precio equivocado',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular factura'}),
    );

    expect(
      await screen.findByText('Factura FE-7 anulada.'),
    ).toBeInTheDocument();
    expect(
      calls.find((c) => c.path === '/sales-invoices/i1/void')?.body,
    ).toEqual({
      reason: 'Precio equivocado',
    });
  });

  it('says when the list could not be loaded', async () => {
    api('owner', {'GET /sales-invoices': [500, {error: 'internal_error'}]});
    renderAt('');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
  });
});

describe('the sales invoice editor', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('warns on a new invoice when the resolution is running out', async () => {
    api('owner', {
      'GET /company/resolution': [
        200,
        resolution({warning: true, numbers_left: 12, days_left: 40}),
      ],
    });
    renderAt('/nueva');

    expect(
      await screen.findByText(
        'Tu resolución de facturación se está acabando: quedan 12 números y 40 días de vigencia.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByDisplayValue('Factura electrónica de venta (FE)'),
    ).toBeInTheDocument();
    for (const name of ['Guardar', 'Emitir', 'Emitir y enviar']) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('warns that nothing can be emitted without a resolution', async () => {
    api('owner', {
      'GET /company/resolution': [
        200,
        {...resolution({status: 'missing'}), resolution: null},
      ],
    });
    renderAt('/nueva');

    expect(
      await screen.findByText(/Aún no has registrado tu resolución/),
    ).toBeInTheDocument();
  });

  it('does not save a draft without its client and shows why', async () => {
    const {calls} = api('owner');
    renderAt('/nueva');
    await screen.findByDisplayValue('Factura electrónica de venta (FE)');

    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));

    expect(
      await screen.findByText('Revisa los campos marcados.'),
    ).toBeInTheDocument();
    expect(calls.some((c) => c.method === 'POST')).toBe(false);
  });

  it('shows an emitted invoice read-only with its actions', async () => {
    api('owner', {'GET /sales-invoices/i1': [200, invoice()]});
    renderAt('/i1');

    expect(
      await screen.findByRole('heading', {name: 'Factura de venta FE-7'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Esta factura ya fue emitida: no se puede modificar/),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Emitir'}),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Descargar PDF'})).toHaveAttribute(
      'href',
      '/api/v1/sales-invoices/i1/pdf',
    );
    for (const name of ['Duplicar', 'Enviar', 'Anular']) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('voids an emitted invoice from its page', async () => {
    api('owner', {
      'GET /sales-invoices/i1': [200, invoice()],
      'POST /sales-invoices/i1/void': [
        200,
        invoice({
          status: 'voided',
          voided_at: '2026-10-03T12:00:00+00:00',
          void_reason: 'Cliente equivocado',
        }),
      ],
    });
    renderAt('/i1');
    await userEvent.click(await screen.findByRole('button', {name: 'Anular'}));
    await userEvent.type(
      screen.getByLabelText('Motivo de la anulación'),
      'Cliente equivocado',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Anular factura'}));

    expect(
      await screen.findByText('Factura FE-7 anulada.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Anulada el 03/10/2026. Motivo: Cliente equivocado'),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
  });

  it('emits a saved draft after asking', async () => {
    const draft = invoice({status: 'draft', number: null, prefix: null});
    const {calls} = api('billing', {
      'GET /sales-invoices/i1': [200, draft],
      'PUT /sales-invoices/i1': [200, draft],
      'POST /sales-invoices/i1/emit': [200, invoice()],
    });
    renderAt('/i1');
    await screen.findByRole('heading', {name: 'Factura de venta · borrador'});
    // The editor's options load before the check can compute Total neto.
    await waitFor(() =>
      expect(calls.some((c) => c.path === '/payment-methods')).toBe(true),
    );

    await userEvent.click(screen.getByRole('button', {name: 'Emitir'}));
    const dialog = await screen.findByRole('dialog', {
      name: '¿Emitir la factura?',
    });
    await userEvent.click(within(dialog).getByRole('button', {name: 'Emitir'}));

    expect(
      await screen.findByText('Factura FE-7 emitida y contabilizada.'),
    ).toBeInTheDocument();
    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual(
      expect.arrayContaining([
        'PUT /sales-invoices/i1',
        'POST /sales-invoices/i1/emit',
      ]),
    );
  });

  it('shows the API refusal inside the emit dialog', async () => {
    const draft = invoice({status: 'draft', number: null, prefix: null});
    api('owner', {
      'GET /sales-invoices/i1': [200, draft],
      'PUT /sales-invoices/i1': [200, draft],
      'POST /sales-invoices/i1/emit': [
        422,
        {error: 'resolution_exhausted', message: 'x'},
      ],
    });
    renderAt('/i1');
    await screen.findByRole('heading', {name: 'Factura de venta · borrador'});

    await userEvent.click(await screen.findByRole('button', {name: 'Emitir'}));
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(within(dialog).getByRole('button', {name: 'Emitir'}));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'La resolución de facturación ya no tiene números disponibles.',
    );
  });

  it('says when the invoice does not exist', async () => {
    api('owner', {
      'GET /sales-invoices/nope': [404, {error: 'sales_invoice_not_found'}],
    });
    renderAt('/nope');

    expect(
      await screen.findByText('Esta factura no existe.'),
    ).toBeInTheDocument();
  });
});
