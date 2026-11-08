import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {LineTaxesDialog} from './LineTaxesDialog';

const tax = (
  id: string,
  name: string,
  taxClass: string,
  rate: string,
  kind = 'iva',
) => ({
  id,
  name,
  tax_class: taxClass,
  kind,
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
  tax(
    'rete4',
    'ReteFuente servicios 4 %',
    'withholding',
    '4.0000',
    'retefuente',
  ),
  tax(
    'rete25',
    'ReteFuente compras 2,5 %',
    'withholding',
    '2.5000',
    'retefuente',
  ),
  tax('reteiva15', 'ReteIVA 15 %', 'withholding', '15.0000', 'reteiva'),
  tax('ica966', 'ReteICA 0,966 %', 'withholding', '0.9660', 'reteica'),
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
      withholdings={[]}
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
    for (const kind of ['Retefuente', 'ReteIVA', 'ReteICA']) {
      expect(
        screen.getByLabelText(kind),
        'one select per kind of retención, "Ninguna" when the line has none',
      ).toHaveValue('');
    }
  });

  it('shows the retenciones the line has, each under its kind', () => {
    fakeApi({});
    renderDialog({withholdings: ['ica966', 'rete25']});

    expect(screen.getByLabelText('Retefuente')).toHaveValue('rete25');
    expect(screen.getByLabelText('ReteIVA')).toHaveValue('');
    expect(screen.getByLabelText('ReteICA')).toHaveValue('ica966');
  });

  it('offers under each kind only the retenciones of that kind', () => {
    fakeApi({});
    renderDialog();

    const options = (label: string) =>
      within(screen.getByLabelText(label))
        .getAllByRole('option')
        .map((o) => o.textContent);
    expect(options('Retefuente')).toEqual([
      'Ninguna',
      'ReteFuente servicios 4 %',
      'ReteFuente compras 2,5 %',
    ]);
    expect(options('ReteIVA')).toEqual(['Ninguna', 'ReteIVA 15 %']);
    expect(options('ReteICA')).toEqual(['Ninguna', 'ReteICA 0,966 %']);
  });

  it('changes the charge tax and the retenciones on the line only, without touching the product', async () => {
    const api = fakeApi({});
    const {onApply} = renderDialog();

    await userEvent.selectOptions(
      screen.getByLabelText('Impuesto cargo'),
      'iva5',
    );
    await userEvent.selectOptions(screen.getByLabelText('ReteICA'), 'ica966');
    await userEvent.selectOptions(screen.getByLabelText('Retefuente'), 'rete4');
    await userEvent.selectOptions(
      screen.getByLabelText('ReteIVA'),
      'reteiva15',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(
      onApply,
      'every retención chosen, in kind order: Retefuente, ReteIVA, ReteICA',
    ).toHaveBeenCalledWith({
      charge_tax_id: 'iva5',
      withholdings: ['rete4', 'reteiva15', 'ica966'],
    });
    expect(api.calls, 'the product keeps its taxes').toHaveLength(0);
  });

  it('saves them on the product from now on when asked', async () => {
    const api = fakeApi({'PUT /products/p1/taxes': [200, {id: 'p1'}]});
    const {onApply} = renderDialog();

    await userEvent.selectOptions(screen.getByLabelText('Impuesto cargo'), '');
    await userEvent.selectOptions(
      screen.getByLabelText('ReteIVA'),
      'reteiva15',
    );
    await userEvent.selectOptions(
      screen.getByLabelText('Retefuente'),
      'rete25',
    );
    await userEvent.click(
      screen.getByLabelText(
        'Aplicar estos impuestos al producto de ahora en adelante',
      ),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(
      api.calls[0]?.body,
      'a product keeps one default retención: the Retefuente one',
    ).toEqual({
      charge_tax_id: null,
      withholding_tax_id: 'rete25',
    });
    expect(onApply).toHaveBeenCalledWith({
      charge_tax_id: null,
      withholdings: ['rete25', 'reteiva15'],
    });
  });

  it('gives the product the first retención chosen when there is no Retefuente', async () => {
    const api = fakeApi({'PUT /products/p1/taxes': [200, {id: 'p1'}]});
    renderDialog({withholdings: ['ica966', 'reteiva15']});

    await userEvent.click(
      screen.getByLabelText(
        'Aplicar estos impuestos al producto de ahora en adelante',
      ),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Aplicar'}));

    expect(api.calls[0]?.body).toEqual({
      charge_tax_id: 'iva19',
      withholding_tax_id: 'reteiva15',
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
