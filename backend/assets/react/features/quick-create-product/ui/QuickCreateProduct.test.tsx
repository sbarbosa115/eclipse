import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {QuickCreateProduct} from './QuickCreateProduct';

const created = {
  id: 'p1',
  type: 'servicio',
  code: 'Q1',
  name: 'Hora de consultoría',
  unit_code: 'ZZ',
  sale_price: '119000.0000',
  price_includes_tax: true,
  unit_price_net_of_tax: '100000.0000',
  active: true,
};

const taxes = {
  'GET /taxes': (_body: unknown, url: URL): [number, unknown] =>
    url.searchParams.get('class') === 'charge'
      ? [200, {items: [{id: 'iva', name: 'IVA 19 %', tax_class: 'charge'}]}]
      : [200, {items: [{id: 'rete', name: 'ReteFuente 4 %'}]}],
};

describe('creating a product from a document line', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('creates it with the company’s default taxes and hands it back', async () => {
    const api = fakeApi({...taxes, 'POST /products/quick': [201, created]});
    const onCreated = vi.fn();
    render(<QuickCreateProduct onCreated={onCreated} onClose={vi.fn()} />);

    await userEvent.selectOptions(screen.getByLabelText('Tipo'), 'servicio');
    await userEvent.type(screen.getByLabelText('Código'), 'Q1');
    await userEvent.type(
      screen.getByLabelText('Nombre'),
      'Hora de consultoría',
    );
    await userEvent.type(
      screen.getByLabelText('Precio de venta (COP)'),
      '119000',
    );
    await userEvent.click(screen.getByLabelText('Incluir IVA en el precio'));
    await userEvent.click(screen.getByRole('button', {name: 'Crear'}));

    expect(onCreated).toHaveBeenCalledWith(created);
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      type: 'servicio',
      code: 'Q1',
      name: 'Hora de consultoría',
      sale_price: '119000',
      price_includes_tax: true,
    });
  });

  it('sends the taxes the person picked', async () => {
    const api = fakeApi({...taxes, 'POST /products/quick': [201, created]});
    render(<QuickCreateProduct onCreated={vi.fn()} onClose={vi.fn()} />);

    await userEvent.type(screen.getByLabelText('Código'), 'Q1');
    await userEvent.type(screen.getByLabelText('Nombre'), 'Algo');
    await userEvent.type(screen.getByLabelText('Precio de venta (COP)'), '10');
    await userEvent.selectOptions(
      await screen
        .findByRole('option', {name: 'IVA 19 %'})
        .then(() => screen.getByLabelText('Impuesto cargo')),
      'iva',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Crear'}));

    expect(api.calls.find((c) => c.method === 'POST')?.body).toMatchObject({
      charge_tax_id: 'iva',
    });
  });

  it('starts from what was typed in the line and checks the form before sending', async () => {
    const api = fakeApi(taxes);
    render(
      <QuickCreateProduct
        initialName="Cuaderno"
        onCreated={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByLabelText('Nombre')).toHaveValue('Cuaderno');
    await userEvent.click(screen.getByRole('button', {name: 'Crear'}));

    expect(screen.getAllByText('Este campo es obligatorio.')).toHaveLength(2);
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);
  });

  it('shows a taken código on its field and stays open', async () => {
    fakeApi({
      ...taxes,
      'POST /products/quick': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'code',
              message: 'Ya hay un producto o servicio con este código.',
            },
          ],
        },
      ],
    });
    const onCreated = vi.fn();
    render(<QuickCreateProduct onCreated={onCreated} onClose={vi.fn()} />);

    await userEvent.type(screen.getByLabelText('Código'), 'Q1');
    await userEvent.type(screen.getByLabelText('Nombre'), 'Algo');
    await userEvent.type(screen.getByLabelText('Precio de venta (COP)'), '10');
    await userEvent.click(screen.getByRole('button', {name: 'Crear'}));

    expect(
      await screen.findByText('Ya hay un producto o servicio con este código.'),
    ).toBeInTheDocument();
    expect(onCreated).not.toHaveBeenCalled();
  });

  it('says so when the role cannot write', async () => {
    fakeApi({...taxes, 'POST /products/quick': [403, {error: 'forbidden'}]});
    render(<QuickCreateProduct onCreated={vi.fn()} onClose={vi.fn()} />);

    await userEvent.type(screen.getByLabelText('Código'), 'Q1');
    await userEvent.type(screen.getByLabelText('Nombre'), 'Algo');
    await userEvent.type(screen.getByLabelText('Precio de venta (COP)'), '10');
    await userEvent.click(screen.getByRole('button', {name: 'Crear'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Tu rol solo puede consultar productos y servicios.',
    );
  });
});
