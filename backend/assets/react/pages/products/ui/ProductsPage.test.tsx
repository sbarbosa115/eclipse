import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {ProductsPage} from './ProductsPage';

const product = (over: Record<string, unknown> = {}) => ({
  id: 'p1',
  type: 'producto',
  code: 'A-1',
  name: 'Cuaderno',
  description: null,
  category_id: null,
  category_name: null,
  unit_code: '94',
  sale_price: '119000.0000',
  price_includes_tax: true,
  unit_price_net_of_tax: '100000.0000',
  charge_tax_id: 'iva',
  withholding_tax_id: null,
  revenue_account_id: null,
  expense_account_id: null,
  active: true,
  revenue_account_label: null,
  expense_account_label: null,
  ...over,
});

const page = (items: unknown[], total = items.length): [number, unknown] => [
  200,
  {items, total, page: 1, per_page: 20},
];

const session = (role: string) => [
  200,
  {
    user_id: 'u',
    email: 'a@b.co',
    name: 'Ana',
    role,
    company_id: 'c',
    permissions: permissionsOf(role),
  },
];

function routes(role: string, products: [number, unknown]) {
  return {
    'GET /me': session(role) as [number, unknown],
    'GET /products': products,
    'GET /products/units': [
      200,
      {
        items: [
          {code: '94', name: 'Unidad'},
          {code: 'ZZ', name: 'Servicio'},
        ],
      },
    ] as [number, unknown],
    'GET /product-categories': [
      200,
      {items: [{id: 'c1', name: 'Papelería', product_count: 3}]},
    ] as [number, unknown],
    'GET /taxes': [200, {items: [{id: 'iva', name: 'IVA 19 %'}]}] as [
      number,
      unknown,
    ],
    'GET /accounts/search': [200, {items: []}] as [number, unknown],
  };
}

const renderPage = (initial = '/productos') =>
  render(
    <MemoryRouter initialEntries={[initial]}>
      <SessionProvider>
        <ProductsPage />
      </SessionProvider>
    </MemoryRouter>,
  );

describe('Productos y servicios', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the catalog with its price and, when it includes IVA, the net value', async () => {
    fakeApi(routes('owner', page([product()])));
    renderPage();

    const row = (await screen.findByText('A-1')).closest('tr') as HTMLElement;
    expect(within(row).getByText('Cuaderno')).toBeInTheDocument();
    expect(within(row).getByText('Unidad')).toBeInTheDocument();
    expect(within(row).getByText('$ 119.000,00')).toBeInTheDocument();
    expect(
      within(row).getByText('IVA incluido · base $ 100.000,00'),
    ).toBeInTheDocument();
  });

  it('shows what the section is for, and how to start, when there is nothing yet', async () => {
    fakeApi(routes('owner', page([])));
    renderPage();

    expect(
      await screen.findByText(/Aún no tienes productos ni servicios/),
    ).toBeInTheDocument();
    expect(
      screen.getAllByRole('button', {name: 'Nuevo producto o servicio'}),
    ).not.toHaveLength(0);
  });

  it('says nothing matched and offers a way back that clears every filter', async () => {
    const api = fakeApi({
      ...routes('owner', page([product()])),
      'GET /products': (_body: unknown, url: URL): [number, unknown] =>
        url.searchParams.get('q') === 'zzz' ? page([]) : page([product()]),
    } as never);
    renderPage('/productos?q=zzz&type=servicio&active=0');

    expect(
      await screen.findByText(
        'Ningún producto o servicio coincide con lo que buscas.',
      ),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));

    expect(await screen.findByText('A-1')).toBeInTheDocument();
    const last = api.calls.filter((c) => c.path === '/products').at(-1);
    expect(last?.url.search, 'no filter is left').toBe('?page=1');
  });

  it('asks the server for what the filters say', async () => {
    const api = fakeApi(routes('owner', page([product()])));
    renderPage();
    await screen.findByText('A-1');

    await userEvent.selectOptions(screen.getByLabelText('Tipo'), 'servicio');
    await userEvent.selectOptions(screen.getByLabelText('Estado'), '0');
    await userEvent.type(screen.getByLabelText('Buscar'), 'cua');

    await vi.waitFor(() => {
      const last = api.calls.filter((c) => c.path === '/products').at(-1);
      expect(last?.url.searchParams.get('type')).toBe('servicio');
      expect(last?.url.searchParams.get('active')).toBe('0');
      expect(last?.url.searchParams.get('q')).toBe('cua');
    });
  });

  it('pages through a long list', async () => {
    const api = fakeApi({
      ...routes('owner', page([product()], 45)),
      'GET /products': (_body: unknown, url: URL): [number, unknown] => [
        200,
        {
          items: [product()],
          total: 45,
          page: Number(url.searchParams.get('page') ?? 1),
          per_page: 20,
        },
      ],
    } as never);
    renderPage();
    await screen.findByText('Página 1 de 3');

    await userEvent.click(screen.getByRole('button', {name: 'Siguiente'}));

    expect(await screen.findByText('Página 2 de 3')).toBeInTheDocument();
    expect(
      api.calls
        .filter((c) => c.path === '/products')
        .at(-1)
        ?.url.searchParams.get('page'),
    ).toBe('2');
  });

  it('shows someone who may only read the catalog without anything to change', async () => {
    fakeApi(routes('reader', page([product()])));
    renderPage();

    await screen.findByText('A-1');
    expect(
      screen.queryByRole('button', {name: 'Nuevo producto o servicio'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Desactivar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Eliminar'}),
    ).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: 'Ver'}));
    expect(await screen.findByLabelText('Nombre')).toBeDisabled();
    expect(
      screen.queryByRole('button', {name: 'Guardar'}),
    ).not.toBeInTheDocument();
  });

  it('creates a product from the full form and shows it saved', async () => {
    const api = fakeApi({
      ...routes('billing', page([])),
      'POST /products': [201, product({code: 'N-1', name: 'Nuevo'})],
    } as never);
    renderPage();
    await userEvent.click(
      (
        await screen.findAllByRole('button', {
          name: 'Nuevo producto o servicio',
        })
      )[0] as HTMLElement,
    );

    await userEvent.type(screen.getByLabelText('Código'), 'N-1');
    await userEvent.type(screen.getByLabelText('Nombre'), 'Nuevo');
    await userEvent.type(
      screen.getByLabelText('Precio de venta (COP)'),
      '119.000,50',
    );
    await userEvent.click(screen.getByLabelText('Incluir IVA en el precio'));
    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));

    expect(await screen.findByText('Producto creado.')).toBeInTheDocument();
    const sent = api.calls.find((c) => c.method === 'POST')?.body as Record<
      string,
      unknown
    >;
    expect(sent).toMatchObject({
      type: 'producto',
      code: 'N-1',
      name: 'Nuevo',
      unit_code: '94',
      sale_price: '119000.50',
      price_includes_tax: true,
    });
    expect(sent, 'the company’s default taxes apply').not.toHaveProperty(
      'charge_tax_id',
    );
  });

  it('shows a taken código on its field', async () => {
    fakeApi({
      ...routes('owner', page([])),
      'POST /products': [
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
    } as never);
    renderPage();
    await userEvent.click(
      (
        await screen.findAllByRole('button', {
          name: 'Nuevo producto o servicio',
        })
      )[0] as HTMLElement,
    );
    await userEvent.type(screen.getByLabelText('Código'), 'A-1');
    await userEvent.type(screen.getByLabelText('Nombre'), 'Otro');
    await userEvent.type(screen.getByLabelText('Precio de venta (COP)'), '1');
    await userEvent.click(screen.getByRole('button', {name: 'Guardar'}));

    expect(
      await screen.findByText('Ya hay un producto o servicio con este código.'),
    ).toBeInTheDocument();
  });

  it('follows the type with the unit while nobody chose one', async () => {
    fakeApi(routes('owner', page([])));
    renderPage();
    await userEvent.click(
      (
        await screen.findAllByRole('button', {
          name: 'Nuevo producto o servicio',
        })
      )[0] as HTMLElement,
    );

    const form = within(await screen.findByRole('dialog'));
    expect(form.getByLabelText('Unidad de medida DIAN')).toHaveValue('94');
    await userEvent.selectOptions(form.getByLabelText('Tipo'), 'servicio');
    expect(form.getByLabelText('Unidad de medida DIAN')).toHaveValue('ZZ');
  });

  it('deactivates a product and says so', async () => {
    const api = fakeApi({
      ...routes('owner', page([product()])),
      'POST /products/p1/deactivate': [200, product({active: false})],
    } as never);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Desactivar'}),
    );

    expect(
      await screen.findByText(
        '«Cuaderno» quedó inactivo: no aparece en documentos nuevos.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.some((c) => c.path === '/products/p1/deactivate')).toBe(
      true,
    );
  });

  it('refuses to delete a product a document uses and offers to deactivate it', async () => {
    const api = fakeApi({
      ...routes('owner', page([product()])),
      'DELETE /products/p1': [409, {error: 'product_in_use'}],
      'POST /products/p1/deactivate': [200, product({active: false})],
    } as never);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Eliminar'}),
    );
    const dialog = await screen.findByRole('dialog');
    expect(
      within(dialog).getByText(/¿Eliminar «Cuaderno»\?/),
    ).toBeInTheDocument();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Eliminar'}),
    );

    expect(
      await within(await screen.findByRole('dialog')).findByText(
        /ya está en documentos: no se puede eliminar/,
      ),
    ).toBeInTheDocument();
    await userEvent.click(
      within(screen.getByRole('dialog')).getByRole('button', {
        name: 'Desactivar',
      }),
    );
    await vi.waitFor(() =>
      expect(api.calls.some((c) => c.path === '/products/p1/deactivate')).toBe(
        true,
      ),
    );
  });

  it('tells a failed load apart from an empty one and retries', async () => {
    fakeApi({
      ...routes('owner', page([])),
      'GET /products': [500, {error: 'internal_error'}],
    } as never);
    renderPage();

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Reintentar'}),
    ).toBeInTheDocument();
  });
});

describe('Categorías', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists, adds and renames', async () => {
    const api = fakeApi({
      ...routes('owner', page([])),
      'POST /product-categories': [
        201,
        {id: 'c2', name: 'Aseo', product_count: 0},
      ],
      'PUT /product-categories/c1': [
        200,
        {id: 'c1', name: 'Útiles', product_count: 3},
      ],
    } as never);
    renderPage();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Categorías'}),
    );
    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findByText('Papelería')).toBeInTheDocument();

    await userEvent.type(
      within(dialog).getByLabelText('Nombre de la categoría'),
      'Aseo',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Agregar categoría'}),
    );
    expect(await within(dialog).findByText('Aseo')).toBeInTheDocument();

    await userEvent.click(
      within(dialog).getAllByRole('button', {
        name: 'Renombrar',
      })[1] as HTMLElement,
    );
    const input = within(dialog).getAllByLabelText(
      'Nombre de la categoría',
    )[1] as HTMLElement;
    await userEvent.clear(input);
    await userEvent.type(input, 'Útiles');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar nombre'}),
    );

    expect(await within(dialog).findByText('Útiles')).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      name: 'Útiles',
    });
  });

  it('shows a repeated name on the field', async () => {
    fakeApi({
      ...routes('owner', page([])),
      'POST /product-categories': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {field: 'name', message: 'Ya hay una categoría con este nombre.'},
          ],
        },
      ],
    } as never);
    renderPage();
    await userEvent.click(
      await screen.findByRole('button', {name: 'Categorías'}),
    );
    const dialog = await screen.findByRole('dialog');
    await userEvent.type(
      within(dialog).getByLabelText('Nombre de la categoría'),
      'Papelería',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Agregar categoría'}),
    );

    expect(
      await within(dialog).findByText('Ya hay una categoría con este nombre.'),
    ).toBeInTheDocument();
  });
});
