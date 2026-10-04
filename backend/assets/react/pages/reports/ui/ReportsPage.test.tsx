import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {permissionsOf} from '@/shared/test/permissions';
import {ReportsPage} from './ReportsPage';

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

const TOTALS = {
  current: '1190000.00',
  days1_to30: '500000.00',
  days31_to60: '0.00',
  days61_to90: '0.00',
  over90: '300000.00',
  total: '1990000.00',
  overdue: '800000.00',
  terceros: 2,
  documents: 3,
};

const row = (over: Record<string, unknown> = {}) => ({
  tercero_id: 't1',
  name: 'Ana Ltda.',
  identification: 'NIT 800197268-4',
  current: '1190000.00',
  days1_to30: '500000.00',
  days31_to60: '0.00',
  days61_to90: '0.00',
  over90: '0.00',
  total: '1690000.00',
  overdue: '500000.00',
  documents: 2,
  ...over,
});

const cartera = (items: unknown[], totals = TOTALS) => ({
  as_of: '2026-10-03',
  items,
  total: items.length,
  page: 1,
  per_page: 25,
  totals,
});

function renderAt(
  path: string,
  role = 'owner',
  extra: Parameters<typeof fakeApi>[0] = {},
) {
  const api = fakeApi({'GET /me': [200, session(role)], ...extra});
  render(
    <MemoryRouter initialEntries={[path]}>
      <SessionProvider>
        <Routes>
          <Route path="reportes/*" element={<ReportsPage />} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
  return api;
}

describe('ReportsPage', () => {
  it('shows cartera de clientes by tercero with its ageing buckets, totals and exports', async () => {
    renderAt('/reportes/clientes', 'owner', {
      'GET /reports/cartera/clients': [
        200,
        cartera([
          row(),
          row({
            tercero_id: 't2',
            name: 'Beto S.A.S.',
            total: '300000.00',
            current: '0.00',
            days1_to30: '0.00',
            over90: '300000.00',
          }),
        ]),
      ],
    });

    expect(await screen.findByText('Ana Ltda.')).toBeInTheDocument();
    for (const header of [
      'Al día',
      '1–30 días',
      '31–60 días',
      '61–90 días',
      'Más de 90 días',
      'Total',
    ]) {
      expect(
        screen.getByRole('columnheader', {name: header}),
      ).toBeInTheDocument();
    }
    const total = within(screen.getAllByRole('row').at(-1)!);
    expect(total.getByText('$ 1.990.000,00')).toBeInTheDocument();
    expect(
      screen
        .getByRole('link', {name: 'Descargar Cartera de clientes en CSV'})
        .getAttribute('href'),
    ).toMatch(
      /^\/api\/v1\/reports\/cartera\/clients\/export\?as_of=\d{4}-\d{2}-\d{2}&format=csv$/,
    );
    expect(
      screen.getByRole('link', {name: 'Ver documentos de Ana Ltda.'}),
    ).toHaveAttribute(
      'href',
      expect.stringMatching(/^\/reportes\/clientes\/t1\?as_of=/),
    );
  });

  it('searches by tercero and asks for the date it was given', async () => {
    const user = userEvent.setup();
    const api = renderAt(
      '/reportes/proveedores?as_of=2026-09-30',
      'accountant',
      {
        'GET /reports/cartera/suppliers': [
          200,
          cartera([row({name: 'Proveedor Uno'})]),
        ],
      },
    );
    await screen.findByText('Proveedor Uno');
    expect(
      api.calls
        .find((c) => c.path === '/reports/cartera/suppliers')!
        .url.searchParams.get('as_of'),
    ).toBe('2026-09-30');
    expect(screen.getByLabelText('Al corte')).toHaveValue('30/09/2026');

    await user.type(screen.getByRole('searchbox'), 'uno');
    await waitFor(() =>
      expect(api.calls.at(-1)!.url.searchParams.get('q')).toBe('uno'),
    );
  });

  it('says what it is when nobody owes anything, and when a search finds nothing', async () => {
    const user = userEvent.setup();
    renderAt('/reportes/clientes', 'billing', {
      'GET /reports/cartera/clients': [
        200,
        cartera([], {...TOTALS, total: '0.00'}),
      ],
    });

    expect(
      await screen.findByText('Ningún cliente te debe nada a esta fecha.'),
    ).toBeInTheDocument();
    await user.type(screen.getByRole('searchbox'), 'zzz');
    expect(
      await screen.findByText('Ningún tercero coincide con la búsqueda.'),
    ).toBeInTheDocument();
  });

  it('drills down to a tercero’s documents with a link to each invoice', async () => {
    renderAt('/reportes/clientes/t1?as_of=2026-10-03', 'owner', {
      'GET /reports/cartera/clients/t1': [
        200,
        {
          as_of: '2026-10-03',
          tercero_id: 't1',
          tercero_name: 'Ana Ltda.',
          total: '1190000.00',
          items: [
            {
              id: 'r1',
              invoice_id: 'i1',
              invoice_number: 'FE-12',
              issue_date: '2026-08-01',
              due_date: '2026-09-01',
              amount: '1190000.00',
              balance: '1190000.00',
              days_overdue: 32,
              bucket: 'days31_to60',
            },
          ],
        },
      ],
    });

    expect(
      await screen.findByRole('heading', {name: 'Ana Ltda.'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'FE-12'})).toHaveAttribute(
      'href',
      '/facturas-venta/i1',
    );
    expect(screen.getByText('32 días vencida')).toBeInTheDocument();
    expect(screen.getByText('01/09/2026')).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Volver a la cartera'}),
    ).toBeInTheDocument();
  });

  it('shows an error with a retry when the report does not load', async () => {
    renderAt('/reportes/clientes', 'owner', {
      'GET /reports/cartera/clients': [500, {error: 'boom'}],
    });

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No pudimos cargar esta información.',
    );
  });

  it('exports every report, the books only for who may view them', async () => {
    renderAt('/reportes/exportar', 'billing');

    expect(
      await screen.findByText('Cartera de clientes por documento'),
    ).toBeInTheDocument();
    expect(screen.queryByText('Libro diario')).not.toBeInTheDocument();
    expect(
      screen.getByText(
        /Los libros contables .* los descargan el administrador y el contador/,
      ),
    ).toBeInTheDocument();
  });

  it('links the ledger books’ exports for the accountant, with the chosen dates', async () => {
    renderAt('/reportes/exportar', 'accountant');

    const csv = await screen.findByRole('link', {
      name: 'Descargar Libro diario en CSV',
    });
    expect(csv.getAttribute('href')).toMatch(
      /^\/api\/v1\/reports\/ledger\/journal\/export\?from=\d{4}-01-01&to=\d{4}-\d{2}-\d{2}&format=csv$/,
    );
    for (const name of [
      'Balance de prueba',
      'Estado de resultados',
      'Balance general',
    ]) {
      expect(
        screen.getByRole('link', {name: `Descargar ${name} en PDF`}),
      ).toBeInTheDocument();
    }
    expect(
      screen
        .getByRole('link', {name: 'Descargar Balance general en CSV'})
        .getAttribute('href'),
    ).toContain('date=');
  });
});
