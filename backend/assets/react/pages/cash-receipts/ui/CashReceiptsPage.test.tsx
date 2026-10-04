import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {CashReceiptsPage} from './CashReceiptsPage';

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

const METHODS = [
  {id: 'cash', name: 'Efectivo', kind: 'cash', active: true, standard: true},
  {
    id: 'bank',
    name: 'Transferencia',
    kind: 'cash',
    active: true,
    standard: true,
  },
  {id: 'credit', name: 'Crédito', kind: 'credit', active: true, standard: true},
];

const CLIENT = {
  id: 't1',
  display_name: 'Cliente Uno S.A.S.',
  person_type: 'empresa',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  roles: ['cliente'],
  active: true,
};

const RECEIVABLES = [
  {
    id: 'r1',
    invoice_id: 'i1',
    invoice_number: 'FE-1',
    issue_date: '2026-09-01',
    due_date: '2026-10-01',
    amount: '595000.00',
    balance: '595000.00',
  },
  {
    id: 'r2',
    invoice_id: 'i2',
    invoice_number: 'FE-2',
    issue_date: '2026-09-15',
    due_date: '2026-10-15',
    amount: '1190000.00',
    balance: '300000.00',
  },
];

const summary = (over: Record<string, unknown> = {}) => ({
  id: 'c1',
  status: 'emitted',
  number: 'RC-1',
  receipt_date: '2026-10-02',
  tercero_id: 't1',
  tercero_name: 'Cliente Uno S.A.S.',
  method_name: 'Efectivo',
  amount: '895000.00',
  invoice_numbers: ['FE-1', 'FE-2'],
  ...over,
});

const receipt = (over: Record<string, unknown> = {}) => ({
  id: 'c1',
  status: 'emitted',
  number: 'RC-1',
  tercero_id: 't1',
  tercero_name: 'Cliente Uno S.A.S.',
  receipt_date: '2026-10-02',
  payment_method_id: 'cash',
  method_name: 'Efectivo',
  amount: '895000.00',
  notes: 'Pago de septiembre',
  allocations: [
    {
      id: 'a1',
      receivable_id: 'r1',
      invoice_id: 'i1',
      invoice_number: 'FE-1',
      amount: '595000.00',
    },
    {
      id: 'a2',
      receivable_id: 'r2',
      invoice_id: 'i2',
      invoice_number: 'FE-2',
      amount: '300000.00',
    },
  ],
  journal_entry_id: 'e1',
  reversal_entry_id: null,
  created_by: 'u1',
  created_at: '2026-10-02T15:00:00+00:00',
  voided_by: null,
  voided_at: null,
  void_reason: null,
  ...over,
});

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

function api(role: string, extra: Parameters<typeof fakeApi>[0] = {}) {
  return fakeApi({
    'GET /me': [200, session(role)],
    'GET /payment-methods': [200, {items: METHODS}],
    'GET /terceros': [200, {items: [CLIENT], total: 1, page: 1, per_page: 10}],
    'GET /cash-receipts/open-receivables': [200, {items: RECEIVABLES}],
    ...extra,
  });
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/recibos-caja${path}`]}>
      <SessionProvider>
        <Routes>
          <Route path="recibos-caja/*" element={<CashReceiptsPage />} />
          <Route path="facturas-venta/:id" element={<p>Factura</p>} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

describe('the recibos de caja list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('says what the section is for when there is nothing yet', async () => {
    api('owner', {'GET /cash-receipts': [200, page([])]});
    renderAt('');

    expect(
      await screen.findByText(/Aún no has registrado recibos de caja/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Registrar el primer recibo'}),
    ).toHaveAttribute('href', '/recibos-caja/nuevo');
    expect(
      screen.getByRole('link', {name: 'Nuevo recibo'}),
    ).toBeInTheDocument();
  });

  it('shows a row with its number, client, invoices, amount and actions', async () => {
    api('owner', {'GET /cash-receipts': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).getByRole('link', {name: 'RC-1'})).toHaveAttribute(
      'href',
      '/recibos-caja/c1',
    );
    expect(row).toHaveTextContent('Emitido');
    expect(row).toHaveTextContent('02/10/2026');
    expect(row).toHaveTextContent('FE-1, FE-2');
    expect(row).toHaveTextContent('895.000');
    expect(within(row).getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/cash-receipts/c1/pdf',
    );
    for (const name of ['Enviar', 'Anular']) {
      expect(within(row).getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('filters by status in the address and offers a way back from nothing', async () => {
    const {calls} = api('owner', {
      'GET /cash-receipts': (_body, url) => [
        200,
        page(url.searchParams.get('status') === 'voided' ? [] : [summary()]),
      ],
    });
    renderAt('');
    await screen.findByText('Cliente Uno S.A.S.');

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'voided');

    expect(
      await screen.findByText('Ningún recibo coincide con estos filtros.'),
    ).toBeInTheDocument();
    expect(calls.at(-1)?.url.searchParams.get('status')).toBe('voided');
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByText('Cliente Uno S.A.S.')).toBeInTheDocument();
  });

  it('voids a receipt from its row, with the reason', async () => {
    const {calls} = api('billing', {
      'GET /cash-receipts': [200, page([summary()])],
      'POST /cash-receipts/c1/void': [200, receipt({status: 'voided'})],
    });
    renderAt('');
    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;

    await userEvent.click(within(row).getByRole('button', {name: 'Anular'}));
    await userEvent.type(
      screen.getByLabelText('Motivo de la anulación'),
      'Cheque devuelto',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Anular recibo'}));

    expect(await screen.findByText('Recibo RC-1 anulado.')).toBeInTheDocument();
    expect(calls.find((c) => c.method === 'POST')?.body).toEqual({
      reason: 'Cheque devuelto',
    });
  });

  it('lets the accountant read and download, nothing more', async () => {
    api('accountant', {'GET /cash-receipts': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).getByRole('link', {name: 'PDF'})).toBeInTheDocument();
    expect(within(row).queryByRole('button')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Nuevo recibo'}),
    ).not.toBeInTheDocument();
  });

  it('says so when the list cannot be loaded', async () => {
    api('owner', {'GET /cash-receipts': [500, {error: 'boom'}]});
    renderAt('');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
  });
});

describe('a new recibo de caja', () => {
  afterEach(() => vi.unstubAllGlobals());

  async function chooseClient() {
    const box = await screen.findByRole('combobox', {name: 'Cliente'});
    await userEvent.type(box, 'Cl');
    expect(
      await screen.findByText('Escribe al menos 3 caracteres.'),
    ).toBeInTheDocument();
    await userEvent.type(box, 'i');
    await userEvent.click(
      await screen.findByRole('option', {name: /Cliente Uno S.A.S./}),
    );
    await screen.findByLabelText('Valor a aplicar a FE-1');
  }

  it('offers only contado methods for where the money comes in', async () => {
    api('owner');
    renderAt('/nuevo');

    const select = await screen.findByLabelText('Dónde ingresa el dinero');
    await waitFor(() =>
      expect(within(select).getAllByRole('option')).toHaveLength(3),
    );
    expect(
      within(select).queryByRole('option', {name: 'Crédito'}),
    ).not.toBeInTheDocument();
    expect(screen.getByLabelText('Fecha')).toHaveValue(
      new Intl.DateTimeFormat('es-CO', {
        timeZone: 'America/Bogota',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(new Date()),
    );
  });

  it('lists the client’s open receivables and saves once the difference is zero', async () => {
    const {calls} = api('owner', {
      'POST /cash-receipts': [201, receipt()],
      'GET /cash-receipts/c1': [200, receipt()],
    });
    renderAt('/nuevo');

    expect(
      await screen.findByText(
        'Elige el cliente para ver sus facturas pendientes.',
      ),
    ).toBeInTheDocument();
    await chooseClient();
    expect(
      calls
        .find((c) => c.path === '/cash-receipts/open-receivables')
        ?.url.searchParams.get('tercero_id'),
    ).toBe('t1');

    await userEvent.selectOptions(
      screen.getByLabelText('Dónde ingresa el dinero'),
      'bank',
    );
    await userEvent.type(screen.getByLabelText('Valor recibido'), '895.000');
    const save = screen.getByRole('button', {name: 'Guardar'});
    expect(save).toBeDisabled();

    await userEvent.click(
      screen.getByRole('button', {name: 'Pagar todo el saldo de FE-1'}),
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'Falta aplicar $ 300.000,00',
    );
    expect(save).toBeDisabled();
    await userEvent.type(
      screen.getByLabelText('Valor a aplicar a FE-2'),
      '300000',
    );
    expect(screen.getByRole('status')).toHaveTextContent('Cuadra');
    expect(save).toBeEnabled();

    await userEvent.type(
      screen.getByLabelText('Observaciones (opcional)'),
      'Pago de septiembre',
    );
    await userEvent.click(save);

    expect(
      await screen.findByText('Recibo RC-1 guardado y contabilizado.'),
    ).toBeInTheDocument();
    expect(calls.find((c) => c.method === 'POST')?.body).toMatchObject({
      tercero_id: 't1',
      payment_method_id: 'bank',
      amount: '895000.00',
      notes: 'Pago de septiembre',
      allocations: [
        {receivable_id: 'r1', amount: '595000.00'},
        {receivable_id: 'r2', amount: '300000.00'},
      ],
      send: false,
    });
  });

  it('saves and sends, and shows a refusal on the row it names', async () => {
    const {calls} = api('billing', {
      'POST /cash-receipts': (body) =>
        (body as {send: boolean}).send
          ? [
              422,
              {
                error: 'validation_failed',
                message: 'x',
                violations: [
                  {
                    field: 'allocations.0.receivable_id',
                    message:
                      'Elige una factura pendiente de este cliente, una sola vez.',
                  },
                ],
              },
            ]
          : [500, {}],
    });
    renderAt('/nuevo');
    await chooseClient();
    await userEvent.selectOptions(
      screen.getByLabelText('Dónde ingresa el dinero'),
      'cash',
    );
    await userEvent.type(screen.getByLabelText('Valor recibido'), '100');
    await userEvent.type(
      screen.getByLabelText('Valor a aplicar a FE-1'),
      '100',
    );

    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar y enviar'}),
    );

    expect(
      await screen.findByText('Revisa los campos marcados.'),
    ).toBeInTheDocument();
    const first = screen.getAllByRole('row')[1]!;
    expect(
      within(first).getByText(
        'Elige una factura pendiente de este cliente, una sola vez.',
      ),
    ).toBeVisible();
    expect(calls.find((c) => c.method === 'POST')?.body).toMatchObject({
      send: true,
    });
  });

  it('says when the client owes nothing', async () => {
    api('owner', {'GET /cash-receipts/open-receivables': [200, {items: []}]});
    renderAt('/nuevo');
    const box = await screen.findByRole('combobox', {name: 'Cliente'});
    await userEvent.type(box, 'Cli');
    await userEvent.click(
      await screen.findByRole('option', {name: /Cliente Uno S.A.S./}),
    );

    expect(
      await screen.findByText(
        'Este cliente no tiene facturas pendientes por cobrar.',
      ),
    ).toBeInTheDocument();
  });
});

describe('a recibo de caja, read-only', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows the receipt with its invoices, PDF and Anular', async () => {
    api('owner', {'GET /cash-receipts/c1': [200, receipt()]});
    renderAt('/c1');

    expect(
      await screen.findByRole('heading', {name: 'Recibo de caja RC-1'}),
    ).toBeInTheDocument();
    expect(screen.getByText('Efectivo')).toBeInTheDocument();
    expect(screen.getByText('Pago de septiembre')).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'FE-2'})).toHaveAttribute(
      'href',
      '/facturas-venta/i2',
    );
    expect(screen.getByRole('link', {name: 'Descargar PDF'})).toHaveAttribute(
      'href',
      '/api/v1/cash-receipts/c1/pdf',
    );
    expect(screen.getByRole('button', {name: 'Anular'})).toBeInTheDocument();
  });

  it('voids it, and then says when and why', async () => {
    api('owner', {
      'GET /cash-receipts/c1': [200, receipt()],
      'POST /cash-receipts/c1/void': [
        200,
        receipt({
          status: 'voided',
          voided_at: '2026-10-03T15:00:00+00:00',
          void_reason: 'Cheque devuelto',
        }),
      ],
    });
    renderAt('/c1');

    await userEvent.click(await screen.findByRole('button', {name: 'Anular'}));
    await userEvent.type(
      screen.getByLabelText('Motivo de la anulación'),
      'Cheque devuelto',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Anular recibo'}));

    expect(
      await screen.findByText('Anulado el 03/10/2026. Motivo: Cheque devuelto'),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
  });

  it('says so for a receipt that does not exist', async () => {
    api('owner', {
      'GET /cash-receipts/zz': [404, {error: 'cash_receipt_not_found'}],
    });
    renderAt('/zz');

    expect(
      await screen.findByText('Este recibo no existe.'),
    ).toBeInTheDocument();
  });
});
