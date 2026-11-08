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

const LAST_YEAR = Number(todayInColombia().slice(0, 4)) - 1;

const row = (over: Record<string, unknown> = {}) => ({
  tercero_id: 't1',
  document_type_code: '31',
  identification_number: '800197268',
  check_digit: '4',
  last_names: null,
  first_names: null,
  business_name: 'Servicios Andinos S.A.S.',
  address: 'Calle 10 # 5-20',
  department_code: '05',
  municipality_code: '05001',
  country_code: 'CO',
  concept_code: '5003',
  account_code: '513595',
  values: {
    payment: '1000000.00',
    vat_cost: '0.00',
    withholding: '25000.00',
    vat_withholding: '28500.00',
  },
  ...over,
});

const paper = (over: Record<string, unknown> = {}) => ({
  year: LAST_YEAR,
  format: '1001',
  threshold: '0.00',
  value_columns: ['payment', 'vat_cost', 'withholding', 'vat_withholding'],
  rows: [row()],
  minor_amounts_rows: [],
  totals: {
    payment: '1000000.00',
    vat_cost: '0.00',
    withholding: '25000.00',
    vat_withholding: '28500.00',
  },
  missing_concepts: 0,
  missing_municipality: 0,
  ...over,
});

function renderAt(path: string, extra: Parameters<typeof fakeApi>[0] = {}) {
  const api = fakeApi({'GET /me': [200, session('accountant')], ...extra});
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

describe('Impuestos › Exógena', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('opens format 1001 of the year that ended, by account and concept, with its files', async () => {
    const api = renderAt('/reportes/impuestos/exogena', {
      'GET /reports/taxes/exogena/1001': [200, paper()],
    });

    expect(await screen.findByText('Servicios Andinos S.A.S.')).toBeVisible();
    expect(screen.getByRole('tab', {name: 'Exógena'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(screen.getByRole('tab', {name: '1001 · Pagos'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    const call = api.calls.find((c) =>
      c.path.startsWith('/reports/taxes/exogena'),
    )!;
    expect(call.url.searchParams.get('year')).toBe(String(LAST_YEAR));
    const line = screen.getByText('Servicios Andinos S.A.S.').closest('tr')!;
    expect(line).toHaveTextContent('5003');
    expect(line).toHaveTextContent('513595');
    expect(line).toHaveTextContent('NIT 800197268-4');
    expect(line).toHaveTextContent('05001');
    expect(line).toHaveTextContent('$ 1.000.000,00');
    const total = within(screen.getAllByRole('row').at(-1)!);
    expect(total.getByText('Total')).toBeInTheDocument();
    expect(total.getByText('$ 28.500,00')).toBeInTheDocument();
    expect(
      screen
        .getByRole('link', {name: 'Descargar Formato 1001 en CSV'})
        .getAttribute('href'),
    ).toBe(
      `/api/v1/reports/taxes/exogena/1001/export?year=${LAST_YEAR}&format=csv`,
    );
  });

  it('switches format and year, and groups the cuantías menores under a threshold', async () => {
    const api = renderAt('/reportes/impuestos/exogena', {
      'GET /reports/taxes/exogena/1001': [200, paper()],
      'GET /reports/taxes/exogena/1008': (_body, url) => [
        200,
        paper({
          format: '1008',
          year: Number(url.searchParams.get('year')),
          threshold: url.searchParams.get('threshold') ?? '0.00',
          value_columns: ['balance'],
          rows: [],
          minor_amounts_rows: url.searchParams.get('threshold')
            ? [
                row({
                  tercero_id: null,
                  document_type_code: '43',
                  identification_number: '222222222',
                  check_digit: null,
                  business_name: 'Cuantías menores',
                  concept_code: null,
                  account_code: null,
                  values: {balance: '1165000.00'},
                }),
              ]
            : [],
          totals: {balance: '1165000.00'},
        }),
      ],
    });
    await screen.findByText('Servicios Andinos S.A.S.');

    await userEvent.click(screen.getByRole('tab', {name: '1008 · Por cobrar'}));
    await userEvent.selectOptions(
      screen.getByLabelText('Año gravable'),
      String(LAST_YEAR - 1),
    );
    const threshold = screen.getByLabelText('Cuantías menores de');
    await userEvent.type(threshold, '500000');
    await userEvent.tab();

    expect(await screen.findByText('Cuantías menores')).toBeVisible();
    const last = api.calls
      .filter((c) => c.path === '/reports/taxes/exogena/1008')
      .at(-1)!;
    expect([
      last.url.searchParams.get('year'),
      last.url.searchParams.get('threshold'),
    ]).toEqual([String(LAST_YEAR - 1), '500000']);
    expect(
      screen.getByText('Cuantías menores').closest('tr'),
    ).toHaveTextContent('222222222');
    expect(
      screen.getByRole('columnheader', {name: 'Saldo al 31/12'}),
    ).toBeVisible();
  });

  it('withholds the files while a tercero has no municipio, and links to them', async () => {
    renderAt('/reportes/impuestos/exogena?format=1009', {
      'GET /reports/taxes/exogena/1009': [
        200,
        paper({
          format: '1009',
          value_columns: ['balance'],
          rows: [
            row({
              municipality_code: null,
              department_code: null,
              values: {balance: '10.00'},
            }),
          ],
          totals: {balance: '10.00'},
          missing_municipality: 2,
        }),
      ],
    });

    expect(
      await screen.findByText(
        /2 terceros de este formato no tienen municipio: complétalos para descargar el formato\./,
      ),
    ).toBeVisible();
    expect(
      screen.getByRole('link', {name: 'Ver terceros sin municipio'}),
    ).toHaveAttribute('href', '/terceros?missing_municipality=1');
    expect(
      screen.queryByRole('link', {name: /Descargar Formato 1009/}),
    ).toBeNull();
    expect(screen.getByText('Sin municipio')).toBeVisible();
  });

  it('says which rows have no concept and where to give them one', async () => {
    renderAt('/reportes/impuestos/exogena', {
      'GET /reports/taxes/exogena/1001': [
        200,
        paper({rows: [row({concept_code: null})], missing_concepts: 1}),
      ],
    });

    expect(
      await screen.findByText(
        /1 fila no tiene concepto DIAN: queda en blanco en el formato\./,
      ),
    ).toBeVisible();
    expect(
      screen.getByRole('link', {name: 'Completar la tabla de conceptos'}),
    ).toHaveAttribute('href', '/configuracion?tab=exogena');
  });

  it('says so when the year has nothing for the format', async () => {
    renderAt('/reportes/impuestos/exogena?format=1005', {
      'GET /reports/taxes/exogena/1005': [
        200,
        paper({
          format: '1005',
          value_columns: ['deductible_vat', 'returned_vat'],
          rows: [],
          totals: {deductible_vat: '0.00', returned_vat: '0.00'},
        }),
      ],
    });

    expect(
      await screen.findByText(
        `Ningún tercero para el formato 1005 en ${LAST_YEAR}.`,
      ),
    ).toBeVisible();
  });

  it('offers a retry when the paper fails to load', async () => {
    renderAt('/reportes/impuestos/exogena', {
      'GET /reports/taxes/exogena/1001': [500, {error: 'boom'}],
    });

    await waitFor(() =>
      expect(screen.getByRole('button', {name: 'Reintentar'})).toBeVisible(),
    );
  });
});
