import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {afterEach, vi} from 'vitest';
import {fakeApi} from '@/shared/test/fakeApi';
import {Actions} from '@/shared/ui';
import {exportUrl} from '../lib/exportUrl';
import {ExportLinks} from './ExportLinks';
import {ReportTable} from './ReportTable';

interface Row {
  id: string;
  name: string;
  amount: string;
}

const ROWS: Row[] = [
  {id: 'a', name: 'Ana', amount: '$ 100,00'},
  {id: 'b', name: 'Beto', amount: '$ 250,00'},
];

const COLUMNS = [
  {header: 'Cliente', cell: (r: Row) => r.name},
  {
    header: 'Saldo',
    numeric: true,
    cell: (r: Row) => r.amount,
    total: '$ 350,00',
  },
];

describe('ReportTable', () => {
  it('shows the rows, a totals row and the CSV and PDF buttons', () => {
    render(
      <ReportTable
        columns={COLUMNS}
        rows={ROWS}
        rowKey={(r) => r.id}
        totalLabel="Total"
        exports={{
          name: 'Cartera',
          csv: '/api/v1/x?format=csv',
          pdf: '/api/v1/x?format=pdf',
        }}
      />,
    );

    const rows = screen.getAllByRole('row');
    expect(rows).toHaveLength(4);
    const total = within(rows[3]!);
    expect(total.getByText('Total')).toBeInTheDocument();
    expect(total.getByText('$ 350,00')).toHaveClass('report-num');
    expect(
      screen.getByRole('link', {name: 'Descargar Cartera en CSV'}),
    ).toHaveAttribute('href', '/api/v1/x?format=csv');
    expect(
      screen.getByRole('link', {name: 'Descargar Cartera en PDF'}),
    ).toHaveAttribute('href', '/api/v1/x?format=pdf');
  });

  it('has no totals row when no column has a total, and no buttons without exports', () => {
    render(
      <ReportTable
        columns={[{header: 'Cliente', cell: (r: Row) => r.name}]}
        rows={ROWS}
        rowKey={(r) => r.id}
      />,
    );

    expect(screen.getAllByRole('row')).toHaveLength(3);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('says so when the report is empty', () => {
    render(
      <ReportTable
        columns={COLUMNS}
        rows={[]}
        rowKey={(r: Row) => r.id}
        empty="Nadie te debe."
      />,
    );

    expect(screen.getByText('Nadie te debe.')).toBeInTheDocument();
  });

  it('adds an actions column when rows have actions', () => {
    render(
      <ReportTable
        columns={COLUMNS}
        rows={ROWS}
        rowKey={(r) => r.id}
        rowActions={(r) => (
          <Actions>
            <a href={`/x/${r.id}`}>Ver {r.name}</a>
          </Actions>
        )}
      />,
    );

    expect(
      screen.getByRole('columnheader', {name: 'Acciones'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Ver Beto'})).toBeInTheDocument();
  });
});

describe('exportUrl', () => {
  it('builds the API address with the filters and the format, skipping blanks', () => {
    expect(
      exportUrl('/reports/cartera/clients/export', 'csv', {
        as_of: '2026-10-03',
        q: '',
        detail: undefined,
      }),
    ).toBe(
      '/api/v1/reports/cartera/clients/export?as_of=2026-10-03&format=csv',
    );
  });

  it('encodes what a person typed', () => {
    expect(exportUrl('/r', 'pdf', {q: 'Ñandú & Cía'})).toBe(
      '/api/v1/r?q=%C3%91and%C3%BA+%26+C%C3%ADa&format=pdf',
    );
  });
});

describe('the export buttons', () => {
  const target = {
    name: 'Cartera',
    csv: '/api/v1/reports/cartera/clients/export?format=csv',
    pdf: '/api/v1/reports/cartera/clients/export?format=pdf',
  };
  const original = window.location;
  afterEach(() => {
    Object.defineProperty(window, 'location', {
      value: original,
      configurable: true,
    });
  });
  const spyOnLocation = () => {
    const assign = vi.fn();
    Object.defineProperty(window, 'location', {
      value: {assign},
      configurable: true,
    });
    return assign;
  };

  it('rehearses the export and then downloads the file', async () => {
    const user = userEvent.setup();
    const api = fakeApi({
      'GET /reports/cartera/clients/export': [204],
    });
    const assign = spyOnLocation();
    render(<ExportLinks target={target} />);

    await user.click(
      screen.getByRole('link', {name: 'Descargar Cartera en CSV'}),
    );

    await waitFor(() => expect(assign).toHaveBeenCalledWith(target.csv));
    expect(api.calls[0]!.url.searchParams.get('check')).toBe('1');
  });

  it('explains a report that is too big instead of downloading the error', async () => {
    const user = userEvent.setup();
    fakeApi({
      'GET /reports/cartera/clients/export': [422, {error: 'export_too_large'}],
    });
    const assign = spyOnLocation();
    render(<ExportLinks target={target} />);

    await user.click(
      screen.getByRole('link', {name: 'Descargar Cartera en PDF'}),
    );

    expect(
      await screen.findByText(/tiene demasiadas filas para una sola descarga/),
    ).toBeInTheDocument();
    expect(assign).not.toHaveBeenCalled();
  });

  it('says so when there is no connection', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));
    const assign = spyOnLocation();
    render(<ExportLinks target={target} />);

    await user.click(
      screen.getByRole('link', {name: 'Descargar Cartera en CSV'}),
    );

    expect(
      await screen.findByText(/No hay conexión con el servidor/),
    ).toBeInTheDocument();
    expect(assign).not.toHaveBeenCalled();
  });
});
