import {render, screen, within} from '@testing-library/react';
import {MemoryRouter} from 'react-router-dom';
import {fakeApi} from '@/shared/test/fakeApi';
import {PurchaseInvoiceList} from './PurchaseInvoiceList';

// Corregir (observations item 7): the list's hint on a voided invoice that was corrected.
const row = (over: Record<string, unknown> = {}) => ({
  id: 'i1',
  status: 'voided',
  number: 'FC-1',
  tercero_id: 't1',
  tercero_name: 'Servicios Andinos S.A.S.',
  supplier_invoice_number: 'FAC-881',
  issue_date: '2026-10-02',
  due_date: '2026-11-01',
  net_total: '1150000.00',
  paid_amount: '0.00',
  balance: '0.00',
  corrected_by_id: null,
  ...over,
});

describe('the purchase invoice list after a Corregir', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('marks the voided invoice Corregida, linking to its replacement', async () => {
    fakeApi({
      'GET /purchase-invoices': [
        200,
        {
          items: [
            row({
              id: 'i9',
              status: 'draft',
              number: null,
            }),
            row({corrected_by_id: 'i9'}),
            row({id: 'i2', number: 'FC-2', supplier_invoice_number: 'X-1'}),
          ],
          total: 3,
          page: 1,
          per_page: 25,
        },
      ],
    });
    render(
      <MemoryRouter initialEntries={['/facturas-compra']}>
        <PurchaseInvoiceList canWrite />
      </MemoryRouter>,
    );

    const rows = within(await screen.findByRole('table')).getAllByRole('row');
    expect(
      within(rows[2]!).getByRole('link', {name: 'Corregida'}),
    ).toHaveAttribute('href', '/facturas-compra/i9');
    expect(
      within(rows[1]!).queryByRole('link', {name: 'Corregida'}),
    ).not.toBeInTheDocument();
    expect(
      within(rows[3]!).queryByRole('link', {name: 'Corregida'}),
    ).not.toBeInTheDocument();
  });
});
