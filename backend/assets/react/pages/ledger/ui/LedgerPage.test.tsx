import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {LedgerPage} from './LedgerPage';

const me = (role: string) =>
  [
    200,
    {
      user_id: 'u',
      email: 'a@b.co',
      name: 'Ana',
      role,
      permissions: permissionsOf(role),
    },
  ] as [number, unknown];

const renderAt = (path: string) =>
  render(
    <MemoryRouter initialEntries={[path]}>
      <SessionProvider>
        <Routes>
          <Route path="contabilidad/*" element={<LedgerPage />} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );

const row = (
  code: string,
  name: string,
  level: string,
  amounts: [string, string, string, string],
) => ({
  code,
  name,
  level,
  nature: 'debit',
  opening: amounts[0],
  debit: amounts[1],
  credit: amounts[2],
  closing: amounts[3],
});

const TRIAL_BALANCE = {
  from: '2026-10-01',
  to: '2026-10-31',
  rows: [
    row('1', 'ACTIVO', 'class', ['0.00', '595000.00', '0.00', '595000.00']),
    row('1305', 'CLIENTES', 'account', [
      '0.00',
      '595000.00',
      '0.00',
      '595000.00',
    ]),
    row('13050501', 'CLIENTES NACIONALES', 'auxiliary', [
      '0.00',
      '595000.00',
      '0.00',
      '595000.00',
    ]),
    row('4', 'INGRESOS', 'class', ['0.00', '0.00', '595000.00', '-595000.00']),
    row('4135', 'COMERCIO AL POR MAYOR Y AL POR MENOR', 'account', [
      '0.00',
      '0.00',
      '595000.00',
      '-595000.00',
    ]),
  ],
  total_debit: '595000.00',
  total_credit: '595000.00',
  balanced: true,
};

const entry = {
  id: 'e1',
  number: 7,
  date: '2026-10-05',
  source_type: 'sales_invoice',
  source_id: 's1',
  source_number: 'FE-2',
  description: 'Factura FE-2',
  reverses_id: null,
  total_debit: '595000.00',
  total_credit: '595000.00',
  lines: [
    {
      account_id: 'a1',
      account_code: '13050501',
      account_name: 'CLIENTES NACIONALES',
      tercero_id: 't1',
      tercero_name: 'Cliente Uno',
      debit: '595000.00',
      credit: '0.00',
      description: null,
    },
    {
      account_id: 'a2',
      account_code: '413595',
      account_name: 'VENTA DE OTROS PRODUCTOS',
      tercero_id: null,
      tercero_name: null,
      debit: '0.00',
      credit: '595000.00',
      description: null,
    },
  ],
};

const journal = (items: unknown[]) =>
  [200, {items, total: items.length, page: 1, per_page: 25}] as [
    number,
    unknown,
  ];

describe('Libros contables', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('is for the owner and the accountant only', async () => {
    const api = fakeApi({'GET /me': me('billing')});
    renderAt('/contabilidad/diario');

    expect(
      await screen.findByText(
        'Los libros contables son del administrador y del contador.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.map((c) => c.path)).toEqual(['/me']);
  });

  it('shows the balance de prueba to the chosen level, and that it balances', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /ledger/trial-balance': [200, TRIAL_BALANCE],
    });
    renderAt('/contabilidad/balance-prueba?from=2026-10-01&to=2026-10-31');

    expect(await screen.findByText('CLIENTES')).toBeInTheDocument();
    expect(screen.queryByText('CLIENTES NACIONALES')).toBeNull();
    expect(screen.getByText('Débitos y créditos cuadran.')).toBeInTheDocument();
    expect(screen.getAllByText('-$ 595.000,00').length).toBeGreaterThan(0);
    expect(api.calls[1]?.url.searchParams.get('from')).toBe('2026-10-01');

    await userEvent.selectOptions(
      screen.getByLabelText('Nivel de detalle'),
      'auxiliary',
    );
    expect(screen.getByText('CLIENTES NACIONALES')).toBeInTheDocument();
  });

  it('drills down from the balance de prueba to the entries of an account', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /ledger/trial-balance': [200, TRIAL_BALANCE],
      'GET /ledger/journal': journal([entry]),
    });
    renderAt('/contabilidad/balance-prueba?from=2026-10-01&to=2026-10-31');

    await userEvent.click(
      await screen.findByRole('link', {name: 'Ver asientos de 4135'}),
    );

    expect(await screen.findByText('FE-2')).toBeInTheDocument();
    const call = api.calls.find((c) => c.path === '/ledger/journal');
    expect(call?.url.searchParams.get('account')).toBe('4135');
    expect(call?.url.searchParams.get('from')).toBe('2026-10-01');
  });

  it('lists the libro diario and filters it by a tercero', async () => {
    const api = fakeApi({
      'GET /me': me('owner'),
      'GET /ledger/journal': (_body, url) =>
        url.searchParams.get('tercero_id') === 't1'
          ? journal([])
          : journal([entry]),
    });
    renderAt('/contabilidad/diario?from=2026-10-01&to=2026-10-31');

    const table = await screen.findByRole('table');
    expect(within(table).getByText('FE-2')).toBeInTheDocument();
    expect(within(table).getByText('Factura de venta')).toBeInTheDocument();
    expect(within(table).getAllByText('$ 595.000,00').length).toBe(4);

    await userEvent.click(
      screen.getByRole('button', {
        name: 'Ver solo los asientos de Cliente Uno',
      }),
    );
    expect(
      await screen.findByText('Ningún asiento coincide con estos filtros.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Tercero: Cliente Uno')).toBeInTheDocument();
    expect(
      api.calls.some((c) => c.url.searchParams.get('tercero_id') === 't1'),
    ).toBe(true);

    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByText('FE-2')).toBeInTheDocument();
  });

  it('tells a new company where entries come from', async () => {
    fakeApi({'GET /me': me('owner'), 'GET /ledger/journal': journal([])});
    renderAt('/contabilidad');

    expect(
      await screen.findByText(
        'Aún no hay asientos. Aparecen cuando emites facturas, recibos y pagos.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('tab', {name: 'Libro diario'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });

  it('shows the estado de resultados', async () => {
    fakeApi({
      'GET /me': me('accountant'),
      'GET /ledger/income-statement': [
        200,
        {
          from: '2026-10-01',
          to: '2026-10-31',
          sections: [
            {
              code: '4',
              name: 'INGRESOS',
              total: '500000.00',
              lines: [
                {
                  code: '41',
                  name: 'OPERACIONALES',
                  level: 'group',
                  amount: '500000.00',
                },
              ],
            },
            {code: '6', name: 'COSTOS DE VENTAS', total: '0.00', lines: []},
            {code: '7', name: 'COSTOS DE PRODUCCION', total: '0.00', lines: []},
            {code: '5', name: 'GASTOS', total: '200000.00', lines: []},
          ],
          revenue: '500000.00',
          costs: '0.00',
          expenses: '200000.00',
          gross_profit: '500000.00',
          net_income: '300000.00',
        },
      ],
    });
    renderAt('/contabilidad/estado-resultados?from=2026-10-01&to=2026-10-31');

    expect(await screen.findByText('41 OPERACIONALES')).toBeInTheDocument();
    expect(
      screen.getByText('Utilidad (pérdida) del periodo').closest('tr'),
    ).toHaveTextContent('$ 300.000,00');
  });

  it('shows the balance general and whether it balances', async () => {
    fakeApi({
      'GET /me': me('accountant'),
      'GET /ledger/balance-sheet': [
        200,
        {
          date: '2026-10-31',
          sections: [
            {code: '1', name: 'ACTIVO', total: '1547000.00', lines: []},
            {code: '2', name: 'PASIVO', total: '247000.00', lines: []},
            {code: '3', name: 'PATRIMONIO', total: '0.00', lines: []},
          ],
          current_earnings: '1300000.00',
          total_assets: '1547000.00',
          total_liabilities: '247000.00',
          total_equity: '1300000.00',
          balanced: true,
        },
      ],
    });
    renderAt('/contabilidad/balance-general?date=2026-10-31');

    expect(
      await screen.findByText('Activo = pasivo + patrimonio.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Resultado del ejercicio').closest('tr'),
    ).toHaveTextContent('$ 1.300.000,00');
  });

  it('offers to retry when a report cannot load', async () => {
    fakeApi({
      'GET /me': me('accountant'),
      'GET /ledger/trial-balance': [500, {error: 'internal_error'}],
    });
    renderAt('/contabilidad/balance-prueba');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
  });
});
