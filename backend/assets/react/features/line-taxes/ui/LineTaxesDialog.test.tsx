import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {LineTaxesDialog} from './LineTaxesDialog';

const tax = (id: string, name: string, taxClass: string, rate: string) => ({
  id,
  name,
  tax_class: taxClass,
  kind: 'iva',
  calculation: 'percentage',
  rate,
  active: true,
  standard: true,
});

const CHARGE = [
  tax('iva19', 'IVA 19 %', 'charge', '19.0000'),
  tax('iva5', 'IVA 5 %', 'charge', '5.0000'),
];
const WITHHOLDING = [
  tax('rete4', 'ReteFuente servicios 4 %', 'withholding', '4.0000'),
  tax('rete25', 'ReteFuente compras 2,5 %', 'withholding', '2.5000'),
];

function renderDialog(
  props: Partial<Parameters<typeof LineTaxesDialog>[0]> = {},
) {
  const onApply = vi.fn();
  const onClose = vi.fn();
  render(
    <LineTaxesDialog
      lineNumber={2}
      subtotal="1800000.00"
      chargeTaxId="iva19"
      withholdingTaxId={null}
      chargeTaxes={CHARGE}
      withholdingTaxes={WITHHOLDING}
      productId="p1"
      onApply={onApply}
      onClose={onClose}
      {...props}
    />,
  );
  return {onApply, onClose};
}

describe('the per-line tax dialog (§4.6)', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows the line’s subtotal and its current taxes', () => {
    fakeApi({});
    renderDialog();

    expect(
      screen.getByRole('dialog', {name: 'Impuestos de la línea 2'}),
    ).toBeInTheDocument();
    expect(screen.getByText('$ 1.800.000,00')).toBeInTheDocument();
    expect(screen.getByLabelText('Impuesto cargo')).toHaveValue('iva19');
    expect(
      screen.getByLabelText('Impuesto retención'),
      'no retención is "Sin impuesto"',
    ).toHaveValue('');
  });

  it('changes both taxes on the line only, without touching the product', async () => {
    const api = fakeApi({});
    const {onApply} = renderDialog();

    await userEvent.selectOptions(
      screen.getByLabelText('Impuesto cargo'),
      'iva5',
    );
    await userEvent.selectOptions(
      screen.getByLabelText('Impuesto retención'),
      'rete4',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(onApply).toHaveBeenCalledWith({
      charge_tax_id: 'iva5',
      withholding_tax_id: 'rete4',
    });
    expect(api.calls, 'the product keeps its taxes').toHaveLength(0);
  });

  it('saves them on the product from now on when asked', async () => {
    const api = fakeApi({'PUT /products/p1/taxes': [200, {id: 'p1'}]});
    const {onApply} = renderDialog();

    await userEvent.selectOptions(screen.getByLabelText('Impuesto cargo'), '');
    await userEvent.selectOptions(
      screen.getByLabelText('Impuesto retención'),
      'rete25',
    );
    await userEvent.click(
      screen.getByLabelText(
        'Aplicar estos impuestos al producto de ahora en adelante',
      ),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(api.calls[0]?.body).toEqual({
      charge_tax_id: null,
      withholding_tax_id: 'rete25',
    });
    expect(onApply).toHaveBeenCalledWith({
      charge_tax_id: null,
      withholding_tax_id: 'rete25',
    });
  });

  it('keeps the dialog open and the line unchanged when the product cannot be saved', async () => {
    fakeApi({'PUT /products/p1/taxes': [403, {error: 'forbidden'}]});
    const {onApply} = renderDialog();

    await userEvent.click(
      screen.getByLabelText(
        'Aplicar estos impuestos al producto de ahora en adelante',
      ),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(
      await screen.findByText(
        'Tu rol no puede cambiar los impuestos del producto. La línea no cambió.',
      ),
    ).toBeInTheDocument();
    expect(onApply).not.toHaveBeenCalled();
  });

  it('offers to update the product only on a line that has one', () => {
    fakeApi({});
    renderDialog({productId: null});

    expect(
      screen.queryByLabelText(
        'Aplicar estos impuestos al producto de ahora en adelante',
      ),
      'a line by expense account has no product to update',
    ).not.toBeInTheDocument();
  });
});
