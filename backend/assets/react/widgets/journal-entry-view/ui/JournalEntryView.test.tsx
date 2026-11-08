import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {DocumentTabs} from './DocumentTabs';
import {JournalEntryView} from './JournalEntryView';

const line = (over: Record<string, unknown> = {}) => ({
  account_id: 'a1',
  account_code: '13050501',
  account_name: 'Clientes nacionales',
  tercero_id: 't1',
  tercero_name: 'Cliente Uno S.A.S.',
  debit: '595000.00',
  credit: '0.00',
  description: null,
  ...over,
});

const entry = (over: Record<string, unknown> = {}) => ({
  id: 'e1',
  number: 7,
  date: '2026-10-05',
  source_type: 'sales_invoice',
  source_id: 's1',
  source_number: 'FE-2',
  description: 'Factura de venta FE-2',
  reverses_id: null,
  total_debit: '595000.00',
  total_credit: '595000.00',
  lines: [
    line(),
    line({
      account_code: '413595',
      account_name: 'Venta de servicios',
      tercero_id: null,
      tercero_name: null,
      debit: '0.00',
      credit: '500000.00',
    }),
    line({
      account_code: '240805',
      account_name: 'IVA generado',
      tercero_id: null,
      tercero_name: null,
      debit: '0.00',
      credit: '95000.00',
    }),
  ],
  ...over,
});

describe('the Contabilización entries', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('asks for the document by source and shows lines and totals', async () => {
    const {calls} = fakeApi({'GET /ledger/entries': [200, {items: [entry()]}]});
    render(<JournalEntryView sourceType="sales_invoice" sourceId="s1" />);

    expect(await screen.findByText(/Asiento 7/)).toBeInTheDocument();
    expect(calls[0]?.url.search).toBe(
      '?source_type=sales_invoice&source_id=s1',
    );
    expect(screen.getByText('13050501')).toBeInTheDocument();
    expect(screen.getByText('Venta de servicios')).toBeInTheDocument();
    expect(screen.getByText('Cliente Uno S.A.S.')).toBeInTheDocument();
    expect(
      screen.getByText('05/10/2026 · Factura de venta FE-2'),
    ).toBeVisible();
    const totals = screen.getByText('Totales').closest('tr') as HTMLElement;
    expect(within(totals).getAllByText('$ 595.000,00')).toHaveLength(2);
    expect(
      screen.getByRole('columnheader', {name: 'Débito'}),
    ).toBeInTheDocument();
  });

  it('lists a void after its entry and says which entry it reverses', async () => {
    fakeApi({
      'GET /ledger/entries': [
        200,
        {
          items: [entry(), entry({id: 'e2', number: 9, reverses_id: 'e1'})],
        },
      ],
    });
    render(<JournalEntryView sourceType="sales_invoice" sourceId="s1" />);

    expect(await screen.findByText(/Asiento 9/)).toBeInTheDocument();
    expect(screen.getByText('Anulación del asiento 7')).toBeInTheDocument();
  });

  it('says so when the document has no entries', async () => {
    fakeApi({'GET /ledger/entries': [200, {items: []}]});
    render(<JournalEntryView sourceType="cash_receipt" sourceId="r1" />);

    expect(
      await screen.findByText('Este documento no tiene asientos contables.'),
    ).toBeInTheDocument();
  });

  it('offers a retry when the entries do not load', async () => {
    let calls = 0;
    fakeApi({
      'GET /ledger/entries': () =>
        ++calls === 1 ? [500, {error: 'boom'}] : [200, {items: [entry()]}],
    });
    render(<JournalEntryView sourceType="sales_invoice" sourceId="s1" />);

    expect(
      await screen.findByText(
        'No pudimos cargar el asiento de este documento.',
      ),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: /reintentar/i}));
    expect(await screen.findByText(/Asiento 7/)).toBeInTheDocument();
  });
});

describe('the document tabs', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows no tabs for a draft or an unsaved document', () => {
    fakeApi({});
    const {rerender} = render(
      <DocumentTabs sourceType="sales_invoice" sourceId="s1" status="draft">
        <p>El documento</p>
      </DocumentTabs>,
    );
    expect(screen.getByText('El documento')).toBeInTheDocument();
    expect(screen.queryByRole('tab')).not.toBeInTheDocument();

    rerender(
      <DocumentTabs sourceType="sales_invoice">
        <p>El documento</p>
      </DocumentTabs>,
    );
    expect(screen.queryByRole('tab')).not.toBeInTheDocument();
  });

  it('switches between Documento and Contabilización once emitted', async () => {
    const {calls} = fakeApi({
      'GET /ledger/entries': [200, {items: [entry()]}],
    });
    render(
      <DocumentTabs sourceType="sales_invoice" sourceId="s1" status="emitted">
        <p>El documento</p>
      </DocumentTabs>,
    );

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual([
      'Documento',
      'Contabilización',
    ]);
    expect(screen.getByText('El documento')).toBeInTheDocument();
    expect(calls).toHaveLength(0);

    await userEvent.click(screen.getByRole('tab', {name: 'Contabilización'}));
    expect(await screen.findByText(/Asiento 7/)).toBeInTheDocument();
    expect(screen.queryByText('El documento')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('tab', {name: 'Documento'}));
    expect(screen.getByText('El documento')).toBeInTheDocument();
  });

  it('reads the entries again when the document is voided', async () => {
    const {calls} = fakeApi({
      'GET /ledger/entries': [200, {items: [entry()]}],
    });
    const {rerender} = render(
      <DocumentTabs
        sourceType="purchase_invoice"
        sourceId="p1"
        status="emitted"
      >
        <p>El documento</p>
      </DocumentTabs>,
    );
    await userEvent.click(screen.getByRole('tab', {name: 'Contabilización'}));
    await screen.findByText(/Asiento 7/);

    rerender(
      <DocumentTabs sourceType="purchase_invoice" sourceId="p1" status="voided">
        <p>El documento</p>
      </DocumentTabs>,
    );
    await screen.findByText(/Asiento 7/);
    expect(calls).toHaveLength(2);
  });
});
