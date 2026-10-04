import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router-dom';
import {fakeApi} from '@/shared/test/fakeApi';
import {PurchaseInvoiceList} from './PurchaseInvoiceList';

const row = (over: Record<string, unknown> = {}) => ({
  id: 'i1',
  status: 'emitted',
  number: 'FC-1',
  tercero_id: 't1',
  tercero_name: 'Servicios Andinos S.A.S.',
  supplier_invoice_number: 'FAC-881',
  issue_date: '2026-10-02',
  due_date: '2026-11-01',
  net_total: '1150000.00',
  paid_amount: '0.00',
  balance: '1150000.00',
  ...over,
});

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

function Where() {
  const location = useLocation();
  return <p data-testid="where">{location.pathname + location.search}</p>;
}

function renderList(writer = true, path = '/facturas-compra') {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route
          path="facturas-compra/*"
          element={
            <>
              <PurchaseInvoiceList canWrite={writer} />
              <Where />
            </>
          }
        />
      </Routes>
    </MemoryRouter>,
  );
}

describe('the purchase invoice list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists invoices with their numbers, supplier, money and actions', async () => {
    fakeApi({
      'GET /purchase-invoices': [
        200,
        page([
          row(),
          row({
            id: 'i2',
            status: 'draft',
            number: null,
            supplier_invoice_number: null,
          }),
        ]),
      ],
    });
    renderList();

    const table = await screen.findByRole('table');
    const rows = within(table).getAllByRole('row');
    expect(rows[1]).toHaveTextContent('FC-1');
    expect(rows[1]).toHaveTextContent('Servicios Andinos S.A.S.');
    expect(rows[1]).toHaveTextContent('FAC-881');
    expect(rows[1]).toHaveTextContent('02/10/2026');
    expect(rows[1]).toHaveTextContent('$ 1.150.000,00');
    expect(
      within(rows[1]!).getByRole('button', {name: 'Anular'}),
    ).toBeInTheDocument();
    expect(within(rows[1]!).getByRole('link', {name: 'PDF'})).toHaveAttribute(
      'href',
      '/api/v1/purchase-invoices/i1/pdf',
    );
    expect(rows[2]).toHaveTextContent('Borrador');
    expect(
      within(rows[2]!).queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
    expect(screen.getByText('Leyenda')).toBeInTheDocument();
  });

  it('offers to record the first invoice when there is none', async () => {
    fakeApi({'GET /purchase-invoices': [200, page([])]});
    renderList();

    expect(
      await screen.findByText(/Aún no has registrado facturas de compra/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {
        name: 'Registrar la primera factura de compra',
      }),
    ).toHaveAttribute('href', '/facturas-compra/nueva');
  });

  it('filters by status and brings everything back with "Ver todo"', async () => {
    const api = fakeApi({
      'GET /purchase-invoices': (_body, url) =>
        url.searchParams.get('status') === 'paid'
          ? [200, page([])]
          : [200, page([row()])],
    });
    renderList();
    await screen.findByRole('table');

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'paid');
    expect(
      await screen.findByText(
        'Ninguna factura de compra coincide con lo que buscas.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.at(-1)?.url.searchParams.get('status')).toBe('paid');

    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByRole('table')).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent(
      /^\/facturas-compra$/,
    );
  });

  it('shows an error with a way to try again', async () => {
    fakeApi({'GET /purchase-invoices': [500, {error: 'internal_error'}]});
    renderList();

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Reintentar'}),
    ).toBeInTheDocument();
  });

  it('voids an invoice after asking why', async () => {
    let voided = false;
    const api = fakeApi({
      'GET /purchase-invoices': () => [
        200,
        page([row(voided ? {status: 'voided', balance: '0.00'} : {})]),
      ],
      'POST /purchase-invoices/i1/void': () => {
        voided = true;
        return [200, {...row(), status: 'voided'}];
      },
    });
    renderList();
    await userEvent.click(await screen.findByRole('button', {name: 'Anular'}));

    const dialog = screen.getByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular factura'}),
    );
    expect(
      within(dialog).getByText('Escribe por qué se anula la factura.'),
    ).toBeInTheDocument();
    await userEvent.type(
      within(dialog).getByLabelText('Motivo de la anulación'),
      'Registrada dos veces',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular factura'}),
    );

    await waitFor(() =>
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
    );
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      reason: 'Registrada dos veces',
    });
    expect(
      await screen.findByText('Factura FC-1 anulada.'),
    ).toBeInTheDocument();
  });

  it('duplicates an invoice into a new draft and opens it', async () => {
    fakeApi({
      'GET /purchase-invoices': [200, page([row()])],
      'POST /purchase-invoices/i1/duplicate': [201, {...row(), id: 'i9'}],
    });
    renderList();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Duplicar'}),
    );

    await waitFor(() =>
      expect(screen.getByTestId('where')).toHaveTextContent(
        '/facturas-compra/i9',
      ),
    );
  });

  it('lets the accountant read only', async () => {
    fakeApi({'GET /purchase-invoices': [200, page([row()])]});
    renderList(false);

    const table = await screen.findByRole('table');
    expect(
      within(table).queryByRole('button', {name: 'Anular'}),
    ).not.toBeInTheDocument();
    expect(
      within(table).queryByRole('button', {name: 'Duplicar'}),
    ).not.toBeInTheDocument();
    expect(
      within(table).getByRole('link', {name: 'Abrir'}),
    ).toBeInTheDocument();
  });
});
