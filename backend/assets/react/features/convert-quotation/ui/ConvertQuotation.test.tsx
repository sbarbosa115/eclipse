import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {ConvertQuotation} from './ConvertQuotation';

function setup(routes: Parameters<typeof fakeApi>[0]) {
  const calls = fakeApi(routes).calls;
  const handlers = {
    onConverted: vi.fn(),
    onRefused: vi.fn(),
    onFailed: vi.fn(),
  };
  render(<ConvertQuotation quotationId="q1" {...handlers} />);
  return {calls, ...handlers};
}

async function confirm() {
  await userEvent.click(
    screen.getByRole('button', {name: 'Convertir a factura'}),
  );
  const dialog = await screen.findByRole('dialog', {
    name: 'Convertir en factura de venta',
  });
  expect(dialog).toHaveTextContent('Una cotización se convierte una sola vez.');
  await userEvent.click(
    within(dialog).getByRole('button', {name: 'Convertir a factura'}),
  );
}

import {within} from '@testing-library/react';

describe('convert a quotation', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('asks first and does nothing until confirmed', async () => {
    const {calls} = setup({});
    await userEvent.click(
      screen.getByRole('button', {name: 'Convertir a factura'}),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Cancelar'}));

    expect(calls).toHaveLength(0);
  });

  it('hands the new draft invoice to the page', async () => {
    const quotation = {
      id: 'q1',
      status: 'accepted',
      converted_invoice_id: 'inv1',
    };
    const {onConverted, calls} = setup({
      'POST /quotations/q1/convert': [200, quotation],
    });

    await confirm();

    await vi.waitFor(() =>
      expect(onConverted).toHaveBeenCalledWith(quotation, 'inv1'),
    );
    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual([
      'POST /quotations/q1/convert',
    ]);
  });

  it('gives the page what the invoice refused, by field', async () => {
    const violations = [
      {field: 'lines.0.product_id', message: 'Este producto está inactivo.'},
      {
        field: 'lines.1.charge_tax_id',
        message: 'Elige un impuesto cargo activo (IVA, impoconsumo).',
      },
    ];
    const {onRefused, onConverted} = setup({
      'POST /quotations/q1/convert': [
        422,
        {error: 'validation_failed', violations},
      ],
    });

    await confirm();

    await vi.waitFor(() => expect(onRefused).toHaveBeenCalledWith(violations));
    expect(onConverted).not.toHaveBeenCalled();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('says in words that it was already converted', async () => {
    const {onFailed} = setup({
      'POST /quotations/q1/convert': [
        409,
        {error: 'quotation_already_converted'},
      ],
    });

    await confirm();

    await vi.waitFor(() =>
      expect(onFailed).toHaveBeenCalledWith(
        'Esta cotización ya se convirtió en factura: solo se convierte una vez.',
      ),
    );
  });
});
