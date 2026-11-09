import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {ConvertPurchaseOrder} from './ConvertPurchaseOrder';

function setup(routes: Parameters<typeof fakeApi>[0]) {
  const calls = fakeApi(routes).calls;
  const handlers = {
    onConverted: vi.fn(),
    onRefused: vi.fn(),
    onFailed: vi.fn(),
  };
  render(<ConvertPurchaseOrder orderId="o1" {...handlers} />);
  return {calls, ...handlers};
}

async function confirm() {
  await userEvent.click(
    screen.getByRole('button', {name: 'Convertir en factura'}),
  );
  const dialog = await screen.findByRole('dialog', {
    name: 'Convertir en factura de compra',
  });
  expect(dialog).toHaveTextContent(
    'Una orden de compra se convierte una sola vez.',
  );
  await userEvent.click(
    within(dialog).getByRole('button', {name: 'Convertir en factura'}),
  );
}

describe('convert a purchase order', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('asks first and does nothing until confirmed', async () => {
    const {calls} = setup({});
    await userEvent.click(
      screen.getByRole('button', {name: 'Convertir en factura'}),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Cancelar'}));

    expect(calls).toHaveLength(0);
  });

  it('hands the new draft invoice to the page', async () => {
    const order = {id: 'o1', status: 'converted', converted_invoice_id: 'inv1'};
    const {onConverted, calls} = setup({
      'POST /purchase-orders/o1/convert': [200, order],
    });

    await confirm();

    await vi.waitFor(() =>
      expect(onConverted).toHaveBeenCalledWith(order, 'inv1'),
    );
    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual([
      'POST /purchase-orders/o1/convert',
    ]);
  });

  it('passes on what the invoice refuses, by field, and converts nothing', async () => {
    const violations = [
      {field: 'tercero_id', message: 'Este tercero está inactivo.'},
    ];
    const {onRefused, onConverted} = setup({
      'POST /purchase-orders/o1/convert': [
        422,
        {error: 'validation_failed', violations},
      ],
    });

    await confirm();

    await vi.waitFor(() => expect(onRefused).toHaveBeenCalledWith(violations));
    expect(onConverted).not.toHaveBeenCalled();
  });

  it('says in words that it was converted already', async () => {
    const {onFailed} = setup({
      'POST /purchase-orders/o1/convert': [
        409,
        {error: 'purchase_order_already_converted'},
      ],
    });

    await confirm();

    await vi.waitFor(() =>
      expect(onFailed).toHaveBeenCalledWith(
        'Esta orden de compra ya se convirtió en factura: solo se convierte una vez.',
      ),
    );
  });
});
