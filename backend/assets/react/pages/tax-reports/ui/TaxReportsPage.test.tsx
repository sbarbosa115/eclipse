import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {todayInColombia} from '@/shared/lib';
import {fakeApi} from '@/shared/test/fakeApi';
import {permissionsOf} from '@/shared/test/permissions';
import {TaxReportsPage} from './TaxReportsPage';

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

const row = (over: Record<string, unknown> = {}) => ({
  tercero_id: 't1',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  tercero_name: 'Servicios Andinos S.A.S.',
  tax_id: 'x1',
  tax_name: 'ReteFuente compras 2,5 %',
  calculation: 'percentage',
  rate: '2.5000',
  base: '1000000.00',
  amount: '25000.00',
  documents: 2,
  ...over,
});

const group = (over: Record<string, unknown> = {}) => ({
  month: '2026-11',
  kind: 'retefuente',
  rows: [row()],
  total_base: '1000000.00',
  total_amount: '25000.00',
  ledger_amount: '25000.00',
  difference: '0.00',
  ...over,
});

const report = (over: Record<string, unknown> = {}) => ({
  from: '2026-11',
  to: '2026-11',
  side: 'practiced',
  kind: null,
  groups: [group()],
  totals: [],
  voided_count: 0,
  ...over,
});

const vat = (over: Record<string, unknown> = {}) => ({
  from: '2026-09',
  to: '2026-10',
  generated: [{rate: '19.0000', base: '2000000.00', tax: '380000.00'}],
  deductible: [{rate: '19.0000', base: '1000000.00', tax: '190000.00'}],
  generated_total: '380000.00',
  deductible_total: '190000.00',
  reteiva_practiced: '0.00',
  reteiva_suffered: '28500.00',
  impoconsumo: '0.00',
  net: '161500.00',
  ledger_generated: '380000.00',
  ledger_deductible: '190000.00',
  generated_difference: '0.00',
  deductible_difference: '0.00',
  voided_count: 0,
  ...over,
});

function renderAt(
  path: string,
  role = 'accountant',
  extra: Parameters<typeof fakeApi>[0] = {},
) {
  const api = fakeApi({'GET /me': [200, session(role)], ...extra});
  render(
    <MemoryRouter initialEntries={[path]}>
      <SessionProvider>
        <Routes>
          <Route path="reportes/impuestos/*" element={<TaxReportsPage />} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
  return api;
}

describe('TaxReportsPage', () => {
  it('opens on retenciones practicadas for this month, with a table per month and kind and the books beside it', async () => {
    const api = renderAt('/reportes/impuestos', 'accountant', {
      'GET /reports/taxes/withholdings/practiced': [200, report()],
    });

    expect(
      await screen.findByRole('region', {name: 'Retefuente · noviembre 2026'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('tab', {name: 'Retenciones practicadas'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    const month = todayInColombia().slice(0, 7);
    const call = api.calls.find(
      (c) => c.path === '/reports/taxes/withholdings/practiced',
    )!;
    expect([call.url.searchParams.get('from'), call.url.searchParams.get('to')]).toEqual([month, month]);
    expect(screen.getByText('Servicios Andinos S.A.S.')).toBeInTheDocument();
    expect(screen.getByText('NIT 800197268-4')).toBeInTheDocument();
    expect(screen.getByText('2,5 %')).toBeInTheDocument();
    const total = within(screen.getAllByRole('row').at(-1)!);
    expect(total.getByText('Total')).toBeInTheDocument();
    expect(total.getByText('$ 25.000,00')).toBeInTheDocument();
    expect(
      screen.getByText('Según libros (2365): $ 25.000,00'),
    ).toBeInTheDocument();
    expect(screen.getByText('Cuadra con los libros.')).toBeInTheDocument();
    expect(
      screen
        .getByRole('link', {name: 'Descargar Retenciones practicadas en PDF'})
        .getAttribute('href'),
    ).toBe(
      `/api/v1/reports/taxes/withholdings/practiced/export?from=${month}&to=${month}&format=pdf`,
    );
    expect(
      screen.getByRole('link', {name: 'Ver facturas de Servicios Andinos S.A.S.'}),
    ).toHaveAttribute(
      'href',
      '/facturas-compra?q=Servicios+Andinos+S.A.S.&from=2026-11-01&to=2026-11-30',
    );
  });

  it('says when the books differ, the voided invoices left out, and the totals of a longer period', async () => {
    renderAt(
      '/reportes/impuestos/retenciones-sufridas?from=2026-10&to=2026-11',
      'owner',
      {
        'GET /reports/taxes/withholdings/suffered': [
          200,
          report({
            from: '2026-10',
            to: '2026-11',
            side: 'suffered',
            groups: [
              group({
                month: '2026-10',
                rows: [],
                total_base: '0.00',
                total_amount: '0.00',
                difference: '25000.00',
              }),
            ],
            totals: [
              {
                kind: 'retefuente',
                total_base: '0.00',
                total_amount: '0.00',
                ledger_amount: '25000.00',
                difference: '25000.00',
              },
            ],
            voided_count: 3,
          }),
        ],
      },
    );

    expect(
      await screen.findByText(
        '3 facturas anuladas en el periodo no se incluyen.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Diferencia con los libros: $ 25.000,00.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Según libros (135515): $ 25.000,00'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {name: 'Totales del periodo'}),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-10');
  });

  it('filters by kind and asks again', async () => {
    const user = userEvent.setup();
    const api = renderAt(
      '/reportes/impuestos/retenciones-practicadas?from=2026-11&to=2026-11',
      'accountant',
      {'GET /reports/taxes/withholdings/practiced': [200, report()]},
    );
    await screen.findByText('Servicios Andinos S.A.S.');

    await user.selectOptions(screen.getByLabelText('Tipo'), 'reteiva');

    await waitFor(() =>
      expect(api.calls.at(-1)!.url.searchParams.get('kind')).toBe('reteiva'),
    );
  });

  it('explains an empty period', async () => {
    renderAt(
      '/reportes/impuestos/retenciones-practicadas?from=2026-11&to=2026-11',
      'accountant',
      {
        'GET /reports/taxes/withholdings/practiced': [
          200,
          report({groups: []}),
        ],
      },
    );

    expect(
      await screen.findByText('No hay retenciones practicadas en el periodo.'),
    ).toBeInTheDocument();
  });

  it('does not ask for a range that ends before it starts', async () => {
    const api = renderAt(
      '/reportes/impuestos/iva?from=2026-11&to=2026-10',
      'accountant',
    );

    expect(
      await screen.findByText(/es anterior al mes «Desde»/),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.path.startsWith('/reports'))).toEqual([]);
    expect(
      screen.queryByRole('link', {name: 'Descargar IVA en CSV'}),
    ).not.toBeInTheDocument();
  });

  it('shows the error and retries', async () => {
    let calls = 0;
    renderAt('/reportes/impuestos/iva', 'accountant', {
      'GET /reports/taxes/vat': () => (++calls === 1 ? [500, {}] : [200, vat()]),
    });

    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', {name: 'Reintentar'}));
    expect(
      await screen.findByRole('region', {name: 'IVA generado (ventas)'}),
    ).toBeInTheDocument();
  });

  it('lays the IVA out by rate with the books and the saldo of the return', async () => {
    renderAt('/reportes/impuestos/iva?from=2026-09&to=2026-10', 'owner', {
      'GET /reports/taxes/vat': [200, vat({net: '-30000.00', voided_count: 1})],
    });

    const generated = within(
      await screen.findByRole('region', {name: 'IVA generado (ventas)'}),
    );
    expect(generated.getByText('19 %')).toBeInTheDocument();
    expect(generated.getByText('$ 2.000.000,00')).toBeInTheDocument();
    expect(
      screen.getByText('Según libros (240810): $ 190.000,00'),
    ).toBeInTheDocument();
    const saldo = within(
      screen.getByRole('region', {name: 'Saldo de la declaración'}),
    );
    expect(saldo.getByText('Saldo a favor')).toBeInTheDocument();
    expect(saldo.getByText('$ 30.000,00')).toBeInTheDocument();
    expect(
      screen.getByText('1 factura anulada en el periodo no se incluye.'),
    ).toBeInTheDocument();
    expect(
      screen
        .getByRole('link', {name: 'Descargar IVA en CSV'})
        .getAttribute('href'),
    ).toBe('/api/v1/reports/taxes/vat/export?from=2026-09&to=2026-10&format=csv');
  });

  it('is only for who may view the books', async () => {
    renderAt('/reportes/impuestos', 'billing');

    expect(
      await screen.findByText(
        'Los reportes de impuestos son del administrador y del contador.',
      ),
    ).toBeInTheDocument();
    expect(screen.queryByRole('tab')).not.toBeInTheDocument();
  });

  it('keeps the period when moving to another tab', async () => {
    const user = userEvent.setup();
    const api = renderAt(
      '/reportes/impuestos/retenciones-practicadas?from=2026-09&to=2026-10',
      'accountant',
      {
        'GET /reports/taxes/withholdings/practiced': [200, report()],
        'GET /reports/taxes/vat': [200, vat()],
      },
    );
    await screen.findByText('Servicios Andinos S.A.S.');

    await user.click(screen.getByRole('tab', {name: 'IVA'}));

    await waitFor(() => {
      const call = api.calls.find((c) => c.path === '/reports/taxes/vat');
      expect(call?.url.searchParams.get('from')).toBe('2026-09');
    });
  });
});
