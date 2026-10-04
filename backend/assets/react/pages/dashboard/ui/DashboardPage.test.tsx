import {render, screen} from '@testing-library/react';
import {MemoryRouter} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {permissionsOf} from '@/shared/test/permissions';
import {DashboardPage} from './DashboardPage';

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

const FIGURES = {
  as_of: '2026-10-03',
  clients_total: '2570000.00',
  clients_overdue: '1190000.00',
  suppliers_total: '2300000.00',
  suppliers_overdue: '0.00',
  sales_month: '2000000.00',
  sales_month_count: 2,
  purchases_month: '1000000.00',
  purchases_month_count: 1,
  cash_and_banks: '1000000.00',
};

const STATUS = {
  status: 'active',
  numbers_left: 900,
  days_left: 200,
  warning: false,
  warning_numbers: 50,
  warning_days: 30,
};

function renderDashboard(
  role: string,
  routes: Parameters<typeof fakeApi>[0] = {},
) {
  const api = fakeApi({
    'GET /me': [200, session(role)],
    'GET /dashboard': [200, FIGURES],
    'GET /company/resolution/status': [200, STATUS],
    ...routes,
  });
  render(
    <MemoryRouter>
      <SessionProvider>
        <DashboardPage />
      </SessionProvider>
    </MemoryRouter>,
  );
  return api;
}

describe('DashboardPage', () => {
  it('shows the numbers of the business in one request', async () => {
    const api = renderDashboard('owner');

    expect(await screen.findByText('$ 2.570.000,00')).toBeInTheDocument();
    expect(screen.getByText('$ 2.300.000,00')).toBeInTheDocument();
    expect(screen.getByText('$ 2.000.000,00')).toBeInTheDocument();
    expect(screen.getByText('Facturas: 2')).toBeInTheDocument();
    expect(screen.getByText('Caja y bancos')).toBeInTheDocument();
    expect(screen.getAllByText('$ 1.000.000,00')).toHaveLength(2); // purchases and cash
    expect(
      screen.getByText('Cómo va tu negocio hoy, 03/10/2026'),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.path === '/dashboard')).toHaveLength(1);
    expect(
      screen.getAllByRole('link', {name: 'Ver cartera'})[0],
    ).toHaveAttribute('href', '/reportes/clientes');
  });

  it('offers the three documents a small company creates most', async () => {
    renderDashboard('billing', {
      'GET /dashboard': [200, {...FIGURES, cash_and_banks: null}],
    });

    expect(
      await screen.findByRole('link', {name: 'Crear factura de venta'}),
    ).toHaveAttribute('href', '/facturas-venta/nueva');
    expect(
      screen.getByRole('link', {name: 'Crear recibo de caja'}),
    ).toHaveAttribute('href', '/recibos-caja/nuevo');
    expect(
      screen.getByRole('link', {name: 'Crear factura de compra'}),
    ).toHaveAttribute('href', '/facturas-compra/nueva');
  });

  it('leaves the books’ figure out for who cannot view the books', async () => {
    renderDashboard('billing', {
      'GET /dashboard': [200, {...FIGURES, cash_and_banks: null}],
    });

    await screen.findByText('$ 2.570.000,00');
    expect(screen.queryByText('Caja y bancos')).not.toBeInTheDocument();
  });

  it('warns when the invoicing resolution is running out', async () => {
    renderDashboard('owner', {
      'GET /company/resolution/status': [
        200,
        {...STATUS, warning: true, numbers_left: 12, days_left: 9},
      ],
    });

    expect(
      await screen.findByText(/te quedan 12 números y 9 días/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Ir a la resolución'}),
    ).toHaveAttribute('href', '/configuracion?tab=resolution');
  });

  it('says when there is no resolution at all, and says nothing when all is well', async () => {
    renderDashboard('billing', {
      'GET /company/resolution/status': [200, {...STATUS, status: 'missing'}],
    });
    expect(
      await screen.findByText(/Aún no has registrado tu resolución/),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Ir a la resolución'}),
    ).not.toBeInTheDocument();
  });

  it('shows no warning for a healthy resolution', async () => {
    renderDashboard('owner');

    await screen.findByText('$ 2.570.000,00');
    expect(screen.queryByText(/resolución/)).not.toBeInTheDocument();
  });

  it('shows an error with a retry when the numbers do not load', async () => {
    renderDashboard('owner', {'GET /dashboard': [500, {error: 'boom'}]});

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No pudimos cargar las cifras del tablero.',
    );
  });
});
