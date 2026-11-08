import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router-dom';
import type {PurchaseInvoice} from '@/entities/purchase-invoice';
import {fakeApi} from '@/shared/test/fakeApi';
import {PurchaseCorrection} from './PurchaseCorrection';

const invoice = (over: Partial<PurchaseInvoice> = {}) =>
  ({
    id: 'i1',
    status: 'emitted',
    number: 'FC-1',
    paid_amount: '0.00',
    net_total: '1150000.00',
    corrected_from_id: null,
    corrected_by_id: null,
    ...over,
  }) as PurchaseInvoice;

function Where() {
  const location = useLocation();
  const notice = (location.state as {notice?: string} | null)?.notice ?? '';
  return (
    <p data-testid="where">
      {location.pathname} {notice}
    </p>
  );
}

function renderFor(value: PurchaseInvoice, canWrite = true) {
  return render(
    <MemoryRouter initialEntries={[`/facturas-compra/${value.id}`]}>
      <Routes>
        <Route
          path="facturas-compra/*"
          element={
            <>
              <PurchaseCorrection invoice={value} canWrite={canWrite} />
              <Where />
            </>
          }
        />
      </Routes>
    </MemoryRouter>,
  );
}

describe('Corregir on a purchase invoice', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('says an emitted invoice is not edited and offers Corregir', () => {
    renderFor(invoice());

    expect(
      screen.getByText(
        'Una factura emitida no se edita. Corregir la anula y abre una copia editable.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Corregir'})).toBeEnabled();
  });

  it('asks why, voids it and opens the editable copy', async () => {
    const api = fakeApi({
      'POST /purchase-invoices/i1/correct': [
        201,
        invoice({id: 'i9', status: 'draft', number: null}),
      ],
    });
    renderFor(invoice());

    await userEvent.click(screen.getByRole('button', {name: 'Corregir'}));
    const dialog = screen.getByRole('dialog');
    expect(
      within(dialog).getByRole('heading', {name: 'Corregir la factura FC-1'}),
    ).toBeInTheDocument();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular y corregir'}),
    );
    expect(
      within(dialog).getByText('Escribe qué se corrige.'),
    ).toBeInTheDocument();
    expect(api.calls).toHaveLength(0);

    await userEvent.type(
      within(dialog).getByLabelText('Qué se corrige'),
      ' Valor mal digitado ',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular y corregir'}),
    );

    await waitFor(() =>
      expect(screen.getByTestId('where')).toHaveTextContent(
        '/facturas-compra/i9 Factura FC-1 anulada. Corrige este borrador y emítelo.',
      ),
    );
    expect(api.calls[0]?.body).toEqual({reason: 'Valor mal digitado'});
  });

  it('shows the refusal in the dialog and stays on the invoice', async () => {
    fakeApi({
      'POST /purchase-invoices/i1/correct': [
        409,
        {error: 'document_has_allocations', message: 'x'},
      ],
    });
    renderFor(invoice());

    await userEvent.click(screen.getByRole('button', {name: 'Corregir'}));
    const dialog = screen.getByRole('dialog');
    await userEvent.type(
      within(dialog).getByLabelText('Qué se corrige'),
      'Valor',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Anular y corregir'}),
    );

    expect(
      await within(dialog).findByText(
        'La factura tiene pagos aplicados: anula primero los recibos de pago.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent(
      /^\/facturas-compra\/i1$/,
    );
  });

  it('tells what to do first on a paid invoice, without the button', () => {
    renderFor(invoice({status: 'paid', paid_amount: '1150000.00'}));

    expect(
      screen.queryByRole('button', {name: 'Corregir'}),
    ).not.toBeInTheDocument();
    expect(
      screen.getByText(
        'Para corregir esta factura, anula primero los recibos de pago que la pagan.',
      ),
    ).toBeInTheDocument();
  });

  it('offers nothing to whoever cannot write purchases', () => {
    renderFor(invoice(), false);

    expect(
      screen.queryByRole('button', {name: 'Corregir'}),
    ).not.toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('links a corrected invoice and its copy to each other', () => {
    const {unmount} = renderFor(
      invoice({status: 'voided', corrected_by_id: 'i9'}),
    );
    expect(
      screen.queryByRole('button', {name: 'Corregir'}),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Ver la factura que la reemplaza'}),
    ).toHaveAttribute('href', '/facturas-compra/i9');
    unmount();

    renderFor(
      invoice({
        id: 'i9',
        status: 'draft',
        number: null,
        corrected_from_id: 'i1',
      }),
    );
    expect(
      screen.getByText(/Esta factura corrige una factura anulada/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Ver la factura original'}),
    ).toHaveAttribute('href', '/facturas-compra/i1');
  });
});
