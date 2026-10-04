import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {QuotationsPage} from './QuotationsPage';

const session = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role,
  permissions: permissionsOf(role),
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

const summary = (over: Record<string, unknown> = {}) => ({
  id: 'q1',
  status: 'emitted',
  number: 'C-7',
  issue_date: '2026-10-01',
  expiry_date: '2026-10-31',
  tercero_id: 't1',
  tercero_name: 'Cliente Uno S.A.S.',
  subtotal: '1000000.00',
  tax_total: '190000.00',
  withholding_total: '0.00',
  net_total: '1190000.00',
  converted_invoice_id: null,
  ...over,
});

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

const quotation = (over: Record<string, unknown> = {}) => ({
  ...summary(),
  prefix: 'C',
  sequence: 7,
  contact_id: null,
  responsible_id: 'e1',
  responsible_name: 'Elena Pérez',
  header: 'Estimada Ana: <b>esta</b> es la propuesta.',
  terms: '50 % de anticipo',
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
    'GET /terceros': [
      200,
      {
        items: [{id: 'e1', display_name: 'Elena Pérez', roles: ['empleado']}],
        total: 1,
        page: 1,
        per_page: 100,
      },
    ],
    'GET /terceros/t1/contacts': [200, {items: []}],
    ...extra,
  });
}

function Where() {
  return <p data-testid="where">{useLocation().pathname}</p>;
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/cotizaciones${path}`]}>
      <SessionProvider>
        <Where />
        <Routes>
          <Route path="cotizaciones/*" element={<QuotationsPage />} />
          <Route path="facturas-venta/:id" element={<p>La factura</p>} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

describe('the quotations list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('says what the section is for when there is nothing yet', async () => {
    api('owner', {'GET /quotations': [200, page([])]});
    renderAt('');

    expect(
      await screen.findByText(/Aún no has hecho cotizaciones/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Crear la primera cotización'}),
    ).toHaveAttribute('href', '/cotizaciones/nueva');
  });

  it('shows a row with its number, client, dates, total and actions', async () => {
    api('owner', {'GET /quotations': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).getByRole('link', {name: 'C-7'})).toBeInTheDocument();
    expect(row).toHaveTextContent('Emitida');
    expect(row).toHaveTextContent('01/10/2026');
    expect(row).toHaveTextContent('31/10/2026');
    expect(row).toHaveTextContent('1.190.000');
    expect(within(row).getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/quotations/q1/pdf',
    );
    for (const name of ['Duplicar', 'Enviar', 'Anular']) {
      expect(within(row).getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('shows an expired offer as such and offers no send', async () => {
    api('owner', {
      'GET /quotations': [200, page([summary({status: 'expired'})])],
    });
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(row).toHaveTextContent('Vencida');
    expect(within(row).queryByRole('button', {name: 'Enviar'})).toBeNull();
    expect(
      within(row).getByRole('button', {name: 'Anular'}),
    ).toBeInTheDocument();
  });

  it('offers nothing to write to someone who may only read', async () => {
    api('reader', {'GET /quotations': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;
    expect(within(row).queryByRole('button')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Nueva cotización'}),
    ).not.toBeInTheDocument();
  });

  it('filters by status, including the expired ones, and offers a way back', async () => {
    const {calls} = api('owner', {
      'GET /quotations': (_body, url) => [
        200,
        page(url.searchParams.get('status') === 'expired' ? [] : [summary()]),
      ],
    });
    renderAt('');
    await screen.findByText('Cliente Uno S.A.S.');

    const filter = screen.getByLabelText('Estado');
    expect(
      within(filter)
        .getAllByRole('option')
        .map((o) => o.textContent),
    ).toEqual([
      'Todos los estados',
      'Borrador',
      'Emitida',
      'Aceptada',
      'Rechazada',
      'Vencida',
      'Anulada',
    ]);
    await userEvent.selectOptions(filter, 'expired');

    expect(
      await screen.findByText('Ninguna cotización coincide con estos filtros.'),
    ).toBeInTheDocument();
    expect(calls.at(-1)?.url.searchParams.get('status')).toBe('expired');
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByText('Cliente Uno S.A.S.')).toBeInTheDocument();
  });

  it('voids a row with a reason', async () => {
    const {calls} = api('owner', {
      'GET /quotations': [200, page([summary()])],
      'POST /quotations/q1/void': [200, quotation({status: 'voided'})],
    });
    renderAt('');
    const row = (await screen.findByText('Cliente Uno S.A.S.')).closest('tr')!;

    await userEvent.click(within(row).getByRole('button', {name: 'Anular'}));
    const dialog = screen.getByRole('dialog', {
      name: 'Anular la cotización C-7',
    });
    await userEvent.type(
      within(dialog).getByLabelText('Motivo de la anulación'),
      'Cambió el alcance',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular cotización'}),
    );

    expect(
      await screen.findByText('Cotización C-7 anulada.'),
    ).toBeInTheDocument();
    expect(calls.find((c) => c.path === '/quotations/q1/void')?.body).toEqual({
      reason: 'Cambió el alcance',
    });
  });

  it('says when the list could not be loaded', async () => {
    api('owner', {'GET /quotations': [500, {error: 'internal_error'}]});
    renderAt('');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
  });
});

describe('the quotation editor', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('starts a new quotation without formas de pago and with its own fields', async () => {
    api('owner');
    renderAt('/nueva');

    expect(await screen.findByDisplayValue('Cotización')).toBeInTheDocument();
    expect(
      screen.getByLabelText(/Responsable de la cotización/),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Válida hasta')).toBeInTheDocument();
    expect(screen.getByLabelText(/Encabezado/)).toBeInTheDocument();
    expect(
      screen.getByLabelText(/Condiciones comerciales/),
    ).toBeInTheDocument();
    expect(screen.queryByText('Formas de pago')).not.toBeInTheDocument();
    for (const name of ['Guardar', 'Emitir', 'Emitir y enviar']) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('offers thirty days of validity and follows the date until it is set by hand', async () => {
    api('owner');
    renderAt('/nueva');
    const expiry = await screen.findByLabelText('Válida hasta');
    const issue = screen.getByLabelText('Fecha de elaboración');

    const today = (issue as HTMLInputElement).value;
    expect(today).toMatch(/^\d{2}\/\d{2}\/\d{4}$/);
    const first = (expiry as HTMLInputElement).value;
    expect(first).toMatch(/^\d{2}\/\d{2}\/\d{4}$/);

    await userEvent.clear(issue);
    await userEvent.type(issue, '01/10/2026');
    expect(expiry).toHaveValue('31/10/2026');

    await userEvent.clear(expiry);
    await userEvent.type(expiry, '15/11/2026');
    await userEvent.clear(issue);
    await userEvent.type(issue, '02/10/2026');
    expect(expiry).toHaveValue('15/11/2026');
  });

  it('does not save a draft without its client and shows why', async () => {
    const {calls} = api('owner');
    renderAt('/nueva');
    await screen.findByDisplayValue('Cotización');

    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));

    expect(
      await screen.findByText('Revisa los campos marcados.'),
    ).toBeInTheDocument();
    expect(calls.some((c) => c.method === 'POST')).toBe(false);
  });

  it('shows an emitted quotation read-only with its actions and its texts as typed', async () => {
    api('owner', {'GET /quotations/q1': [200, quotation()]});
    renderAt('/q1');

    expect(
      await screen.findByRole('heading', {name: 'Cotización C-7'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Esta cotización ya fue emitida: no se puede modificar/),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Emitir'}),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Descargar PDF'})).toHaveAttribute(
      'href',
      '/api/v1/quotations/q1/pdf',
    );
    for (const name of [
      'Duplicar',
      'Enviar',
      'Aceptar',
      'Rechazar',
      'Convertir a factura',
      'Anular',
    ]) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
    // Plain text: markup typed by a person is shown as characters, never interpreted.
    expect(screen.getByLabelText(/Encabezado/)).toHaveValue(
      'Estimada Ana: <b>esta</b> es la propuesta.',
    );
    expect(document.querySelector('b')).toBeNull();
    expect(screen.getByLabelText(/Responsable de la cotización/)).toHaveValue(
      'e1',
    );
  });

  it('says an offer lapsed and still lets it be accepted late', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation({status: 'expired'})],
    });
    renderAt('/q1');

    expect(
      await screen.findByText(/La oferta venció el 31\/10\/2026/),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Aceptar'})).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Convertir a factura'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Anular'})).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: 'Enviar'})).toBeNull();
  });

  it('accepts after asking', async () => {
    const {calls} = api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/accept': [200, quotation({status: 'accepted'})],
    });
    renderAt('/q1');

    await userEvent.click(await screen.findByRole('button', {name: 'Aceptar'}));
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Aceptar'}),
    );

    expect(
      await screen.findByText('Cotización C-7 aceptada.'),
    ).toBeInTheDocument();
    expect(calls.some((c) => c.path === '/quotations/q1/accept')).toBe(true);
    expect(
      screen.getByRole('button', {name: 'Convertir a factura'}),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: 'Rechazar'})).toBeNull();
  });

  it('rejects after asking', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/reject': [200, quotation({status: 'rejected'})],
    });
    renderAt('/q1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Rechazar'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Rechazar'}),
    );

    expect(
      await screen.findByText('Cotización C-7 rechazada.'),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Convertir a factura'}),
    ).toBeNull();
  });

  it('converts into a draft invoice and opens it', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/convert': [
        200,
        quotation({status: 'accepted', converted_invoice_id: 'inv1'}),
      ],
    });
    renderAt('/q1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Convertir a factura'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Convertir a factura'}),
    );

    expect(await screen.findByText('La factura')).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent(
      '/facturas-venta/inv1',
    );
  });

  it('lists what the invoice refused, by line, on the quotation page', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/convert': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'lines.0.product_id',
              message: 'Este producto está inactivo.',
            },
            {field: 'tercero_id', message: 'Este cliente está inactivo.'},
          ],
        },
      ],
    });
    renderAt('/q1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Convertir a factura'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Convertir a factura'}),
    );

    const alert = await screen.findByText('No se pudo convertir la cotización');
    const box = alert.closest('[role="alert"]') as HTMLElement;
    expect(
      within(box).getByText('Línea 1: Este producto está inactivo.'),
    ).toBeInTheDocument();
    expect(
      within(box).getByText('Este cliente está inactivo.'),
    ).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent('/cotizaciones/q1');
    expect(
      screen.getByRole('button', {name: 'Convertir a factura'}),
    ).toBeInTheDocument();
  });

  it('links an accepted quotation to its invoice and offers no second conversion', async () => {
    api('owner', {
      'GET /quotations/q1': [
        200,
        quotation({status: 'accepted', converted_invoice_id: 'inv1'}),
      ],
    });
    renderAt('/q1');

    const link = await screen.findByRole('link', {name: 'Ver la factura'});
    expect(link).toHaveAttribute('href', '/facturas-venta/inv1');
    expect(
      screen.queryByRole('button', {name: 'Convertir a factura'}),
    ).toBeNull();
  });

  it('says the quotation was already converted when another tab did it first', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/convert': [
        409,
        {error: 'quotation_already_converted'},
      ],
    });
    renderAt('/q1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Convertir a factura'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Convertir a factura'}),
    );

    expect(
      await screen.findByText(
        'Esta cotización ya se convirtió en factura: solo se convierte una vez.',
      ),
    ).toBeInTheDocument();
  });

  it('voids an emitted quotation from its page', async () => {
    api('owner', {
      'GET /quotations/q1': [200, quotation()],
      'POST /quotations/q1/void': [
        200,
        quotation({
          status: 'voided',
          voided_at: '2026-10-03T12:00:00+00:00',
          void_reason: 'Cliente equivocado',
        }),
      ],
    });
    renderAt('/q1');
    await userEvent.click(await screen.findByRole('button', {name: 'Anular'}));
    await userEvent.type(
      screen.getByLabelText('Motivo de la anulación'),
      'Cliente equivocado',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Anular cotización'}),
    );

    expect(
      await screen.findByText('Cotización C-7 anulada.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Anulada el 03/10/2026. Motivo: Cliente equivocado'),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
  });

  it('emits a saved draft after asking', async () => {
    const draft = quotation({status: 'draft', number: null, prefix: null});
    const {calls} = api('billing', {
      'GET /quotations/q1': [200, draft],
      'PUT /quotations/q1': [200, draft],
      'POST /quotations/q1/emit': [200, quotation()],
    });
    renderAt('/q1');
    await screen.findByRole('heading', {name: 'Cotización · borrador'});
    await waitFor(() =>
      expect(calls.some((c) => c.path === '/taxes')).toBe(true),
    );

    await userEvent.click(screen.getByRole('button', {name: 'Emitir'}));
    const dialog = await screen.findByRole('dialog', {
      name: '¿Emitir la cotización?',
    });
    await userEvent.click(within(dialog).getByRole('button', {name: 'Emitir'}));

    expect(
      await screen.findByText('Cotización C-7 emitida.'),
    ).toBeInTheDocument();
    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual(
      expect.arrayContaining([
        'PUT /quotations/q1',
        'POST /quotations/q1/emit',
      ]),
    );
    const saved = calls.find((c) => c.method === 'PUT')?.body as Record<
      string,
      unknown
    >;
    expect(saved).toMatchObject({
      responsible_id: 'e1',
      expiry_date: '2026-10-31',
      terms: '50 % de anticipo',
    });
    expect(saved).not.toHaveProperty('payments');
  });

  it('shows the API refusal inside the emit dialog', async () => {
    const draft = quotation({status: 'draft', number: null, prefix: null});
    api('owner', {
      'GET /quotations/q1': [200, draft],
      'PUT /quotations/q1': [200, draft],
      'POST /quotations/q1/emit': [422, {error: 'tercero_inactive'}],
    });
    renderAt('/q1');
    await screen.findByRole('heading', {name: 'Cotización · borrador'});

    await userEvent.click(await screen.findByRole('button', {name: 'Emitir'}));
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(within(dialog).getByRole('button', {name: 'Emitir'}));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'El cliente está inactivo: actívalo para cotizarle.',
    );
  });

  it('gives someone who may only read a read-only view', async () => {
    api('reader', {'GET /quotations/q1': [200, quotation()]});
    renderAt('/q1');

    await screen.findByRole('heading', {name: 'Cotización C-7'});
    for (const name of [
      'Duplicar',
      'Enviar',
      'Aceptar',
      'Anular',
      'Convertir a factura',
    ]) {
      expect(screen.queryByRole('button', {name})).toBeNull();
    }
    expect(
      screen.getByRole('link', {name: 'Descargar PDF'}),
    ).toBeInTheDocument();
  });

  it('says when the quotation does not exist', async () => {
    api('owner', {
      'GET /quotations/nope': [404, {error: 'quotation_not_found'}],
    });
    renderAt('/nope');

    expect(
      await screen.findByText('Esta cotización no existe.'),
    ).toBeInTheDocument();
  });
});
