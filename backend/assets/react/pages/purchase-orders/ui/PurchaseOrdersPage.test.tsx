import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {PurchaseOrdersPage} from './PurchaseOrdersPage';

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
  id: 'o1',
  status: 'emitted',
  number: 'OC-3',
  tercero_id: 't1',
  tercero_name: 'Papelería Andina S.A.S.',
  issue_date: '2026-11-02',
  expected_date: '2026-11-20',
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

const order = (over: Record<string, unknown> = {}) => ({
  ...summary(),
  notes: 'Entregar en bodega',
  gross_total: '1000000.00',
  discount_total: '0.00',
  subtotal: '1000000.00',
  tax_total: '190000.00',
  withholding_total: '0.00',
  lines: [
    {
      id: 'l1',
      position: 1,
      product_id: 'p1',
      product_label: 'RES-01 · Resma carta',
      product_code: 'RES-01',
      account_id: null,
      description: 'Resmas',
      quantity: '1.0000',
      unit_price: '1000000.0000',
      discount: '0.0000',
      charge_tax_id: 'iva19',
      charge_tax_name: 'IVA 19 %',
      withholdings: [],
    },
  ],
  withholdings_by_tax: [],
  created_at: '2026-11-02T10:00:00+00:00',
  emitted_at: '2026-11-02T10:00:00+00:00',
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
    'GET /payment-methods': [200, {items: []}],
    'GET /terceros/t1/contacts': [200, {items: []}],
    ...extra,
  });
}

function Where() {
  return <p data-testid="where">{useLocation().pathname}</p>;
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/ordenes-compra${path}`]}>
      <SessionProvider>
        <Where />
        <Routes>
          <Route path="ordenes-compra/*" element={<PurchaseOrdersPage />} />
          <Route path="facturas-compra/:id" element={<p>La factura</p>} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

describe('the purchase orders list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('says what the section is for when there is nothing yet', async () => {
    api('owner', {'GET /purchase-orders': [200, page([])]});
    renderAt('');

    expect(
      await screen.findByText(/Aún no has hecho órdenes de compra/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Crear la primera orden de compra'}),
    ).toHaveAttribute('href', '/ordenes-compra/nueva');
  });

  it('shows a row with its number, supplier, dates, total and actions', async () => {
    api('owner', {'GET /purchase-orders': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Papelería Andina S.A.S.')).closest(
      'tr',
    )!;
    expect(within(row).getByRole('link', {name: 'OC-3'})).toBeInTheDocument();
    expect(row).toHaveTextContent('Emitida');
    expect(row).toHaveTextContent('02/11/2026');
    expect(row).toHaveTextContent('20/11/2026');
    expect(row).toHaveTextContent('1.190.000');
    expect(within(row).getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/purchase-orders/o1/pdf',
    );
    for (const name of ['Duplicar', 'Enviar', 'Anular']) {
      expect(within(row).getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('offers neither send nor void on a converted order', async () => {
    api('owner', {
      'GET /purchase-orders': [
        200,
        page([summary({status: 'converted', converted_invoice_id: 'i1'})]),
      ],
    });
    renderAt('');

    const row = (await screen.findByText('Papelería Andina S.A.S.')).closest(
      'tr',
    )!;
    expect(row).toHaveTextContent('Convertida');
    expect(within(row).queryByRole('button', {name: 'Enviar'})).toBeNull();
    expect(within(row).queryByRole('button', {name: 'Anular'})).toBeNull();
    expect(
      within(row).getByRole('button', {name: 'Duplicar'}),
    ).toBeInTheDocument();
  });

  it('offers nothing to write to someone who may only read', async () => {
    api('reader', {'GET /purchase-orders': [200, page([summary()])]});
    renderAt('');

    const row = (await screen.findByText('Papelería Andina S.A.S.')).closest(
      'tr',
    )!;
    expect(within(row).queryByRole('button')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Nueva orden de compra'}),
    ).not.toBeInTheDocument();
  });

  it('filters by the four statuses and offers a way back', async () => {
    const {calls} = api('owner', {
      'GET /purchase-orders': (_body, url) => [
        200,
        page(url.searchParams.get('status') === 'voided' ? [] : [summary()]),
      ],
    });
    renderAt('');
    await screen.findByText('Papelería Andina S.A.S.');

    const filter = screen.getByLabelText('Estado');
    expect(
      within(filter)
        .getAllByRole('option')
        .map((o) => o.textContent),
    ).toEqual([
      'Todos los estados',
      'Borrador',
      'Emitida',
      'Convertida',
      'Anulada',
    ]);
    await userEvent.selectOptions(filter, 'voided');

    expect(
      await screen.findByText(
        'Ninguna orden de compra coincide con estos filtros.',
      ),
    ).toBeInTheDocument();
    expect(calls.at(-1)?.url.searchParams.get('status')).toBe('voided');
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(
      await screen.findByText('Papelería Andina S.A.S.'),
    ).toBeInTheDocument();
  });

  it('voids a row with a reason', async () => {
    const {calls} = api('owner', {
      'GET /purchase-orders': [200, page([summary()])],
      'POST /purchase-orders/o1/void': [200, order({status: 'voided'})],
    });
    renderAt('');
    const row = (await screen.findByText('Papelería Andina S.A.S.')).closest(
      'tr',
    )!;

    await userEvent.click(within(row).getByRole('button', {name: 'Anular'}));
    const dialog = screen.getByRole('dialog', {
      name: 'Anular la orden de compra OC-3',
    });
    await userEvent.type(
      within(dialog).getByLabelText('Motivo de la anulación'),
      'El proveedor ya no vende',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular orden de compra'}),
    );

    expect(
      await screen.findByText('Orden de compra OC-3 anulada.'),
    ).toBeInTheDocument();
    expect(
      calls.find((c) => c.path === '/purchase-orders/o1/void')?.body,
    ).toEqual({reason: 'El proveedor ya no vende'});
  });

  it('says when the list could not be loaded', async () => {
    api('owner', {'GET /purchase-orders': [500, {error: 'internal_error'}]});
    renderAt('');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
  });
});

describe('the purchase order editor', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('starts a new order without formas de pago and with its expected date', async () => {
    api('owner');
    renderAt('/nueva');

    expect(
      await screen.findByDisplayValue('Orden de compra'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/Entrega esperada/)).toBeInTheDocument();
    expect(screen.getByText('Proveedor')).toBeInTheDocument();
    expect(screen.queryByText('Formas de pago')).not.toBeInTheDocument();
    for (const name of ['Guardar', 'Emitir', 'Emitir y enviar']) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
  });

  it('does not save a draft without its supplier and shows why', async () => {
    const {calls} = api('owner');
    renderAt('/nueva');
    await screen.findByDisplayValue('Orden de compra');

    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));

    expect(
      await screen.findByText('Revisa los campos marcados.'),
    ).toBeInTheDocument();
    expect(calls.some((c) => c.method === 'POST')).toBe(false);
  });

  it('shows an emitted order read-only with its actions', async () => {
    api('owner', {'GET /purchase-orders/o1': [200, order()]});
    renderAt('/o1');

    expect(
      await screen.findByRole('heading', {name: 'Orden de compra OC-3'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        /Esta orden de compra ya fue emitida: no se puede modificar/,
      ),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Guardar'}),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Descargar PDF'})).toHaveAttribute(
      'href',
      '/api/v1/purchase-orders/o1/pdf',
    );
    for (const name of [
      'Duplicar',
      'Enviar',
      'Convertir en factura',
      'Anular',
    ]) {
      expect(screen.getByRole('button', {name})).toBeInTheDocument();
    }
    expect(screen.getByLabelText(/Entrega esperada/)).toHaveValue('20/11/2026');
    expect(screen.queryByText('Contabilización')).not.toBeInTheDocument();
  });

  it('links a converted order to its invoice and offers no more conversion', async () => {
    api('owner', {
      'GET /purchase-orders/o1': [
        200,
        order({status: 'converted', converted_invoice_id: 'inv1'}),
      ],
    });
    renderAt('/o1');

    expect(
      await screen.findByText(/La orden de compra se convirtió en factura/),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Ver la factura'})).toHaveAttribute(
      'href',
      '/facturas-compra/inv1',
    );
    for (const name of ['Convertir en factura', 'Anular', 'Enviar']) {
      expect(screen.queryByRole('button', {name})).toBeNull();
    }
  });

  it('opens the draft invoice after converting', async () => {
    const {calls} = api('owner', {
      'GET /purchase-orders/o1': [200, order()],
      'POST /purchase-orders/o1/convert': [
        200,
        order({status: 'converted', converted_invoice_id: 'inv1'}),
      ],
    });
    renderAt('/o1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Convertir en factura'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Convertir en factura'}),
    );

    expect(await screen.findByText('La factura')).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent(
      '/facturas-compra/inv1',
    );
    expect(
      calls.filter((c) => c.path === '/purchase-orders/o1/convert'),
    ).toHaveLength(1);
  });

  it('lists what the invoice refused, by line, and keeps the order open', async () => {
    api('owner', {
      'GET /purchase-orders/o1': [200, order()],
      'POST /purchase-orders/o1/convert': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {field: 'tercero_id', message: 'Este tercero está inactivo.'},
            {
              field: 'lines[0].product_id',
              message: 'Este producto está inactivo.',
            },
          ],
        },
      ],
    });
    renderAt('/o1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Convertir en factura'}),
    );
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', {
        name: 'Convertir en factura',
      }),
    );

    expect(
      await screen.findByText('No se pudo convertir la orden de compra'),
    ).toBeInTheDocument();
    expect(screen.getByText('Este tercero está inactivo.')).toBeInTheDocument();
    expect(
      screen.getByText('Línea 1: Este producto está inactivo.'),
    ).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent('/ordenes-compra/o1');
  });

  it('says an order is not found', async () => {
    api('owner', {
      'GET /purchase-orders/nope': [404, {error: 'purchase_order_not_found'}],
    });
    renderAt('/nope');

    expect(
      await screen.findByText('Esta orden de compra no existe.'),
    ).toBeInTheDocument();
  });
});
