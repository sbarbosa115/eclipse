import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {ChartOfAccounts} from './ChartOfAccounts';

const account = (code: string, name: string, extra = {}) => ({
  id: `id-${code}`,
  code,
  name,
  nature: 'debit',
  level:
    code.length === 1
      ? 'class'
      : code.length === 2
        ? 'group'
        : code.length === 4
          ? 'account'
          : code.length === 6
            ? 'subaccount'
            : 'auxiliary',
  parent_code: code.length > 1 ? code.slice(0, -2) || code[0] : null,
  standard: code.length <= 6,
  active: true,
  postable: code.length >= 6,
  usable_on_purchases: false,
  ...extra,
});

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 50,
});

const me = (role: string) =>
  [
    200,
    {
      user_id: 'u',
      email: 'a@b.co',
      name: 'Ana',
      role,
      permissions: permissionsOf(role),
    },
  ] as [number, unknown];

const renderChart = () =>
  render(
    <SessionProvider>
      <ChartOfAccounts />
    </SessionProvider>,
  );

describe('Plan de cuentas', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the chart in code order and searches it', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /accounts': (_body, url) =>
        url.searchParams.get('q') === 'caja'
          ? [200, page([account('110505', 'CAJA GENERAL')])]
          : [
              200,
              page([
                account('1105', 'CAJA'),
                account('110505', 'CAJA GENERAL'),
              ]),
            ],
    });
    renderChart();

    expect(await screen.findByText('CAJA GENERAL')).toBeInTheDocument();
    expect(screen.getByText('1105')).toBeInTheDocument();
    await userEvent.type(screen.getByRole('searchbox'), 'caja');

    await waitFor(() =>
      expect(screen.queryByText('1105')).not.toBeInTheDocument(),
    );
    expect(api.calls.some((c) => c.url.searchParams.get('q') === 'caja')).toBe(
      true,
    );
  });

  it('says when nothing matches and offers to show everything', async () => {
    fakeApi({
      'GET /me': me('accountant'),
      'GET /accounts': (_body, url) =>
        url.searchParams.get('class') === '9'
          ? [200, page([])]
          : [200, page([account('1105', 'CAJA')])],
    });
    renderChart();
    await screen.findByText('CAJA');

    await userEvent.selectOptions(screen.getByLabelText('Clase'), '9');

    expect(
      await screen.findByText('Ninguna cuenta coincide con la búsqueda.'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));
    expect(await screen.findByText('CAJA')).toBeInTheDocument();
  });

  it('adds an auxiliar under a subcuenta', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /accounts': [200, page([account('111005', 'MONEDA NACIONAL')])],
      'POST /accounts': (body) => [
        201,
        account('11100502', (body as {name: string}).name),
      ],
    });
    renderChart();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Agregar subcuenta bajo 111005',
      }),
    );
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByLabelText('Código')).toHaveValue('111005');
    await userEvent.type(within(dialog).getByLabelText('Código'), '02');
    await userEvent.type(
      within(dialog).getByLabelText('Nombre'),
      'Bancolombia ahorros',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      await screen.findByText('Cuenta 11100502 creada.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      parent_code: '111005',
      code: '11100502',
      name: 'Bancolombia ahorros',
      usable_on_purchases: false,
      move_references: true,
    });
  });

  it('explains a refused code in the form', async () => {
    fakeApi({
      'GET /me': me('owner'),
      'GET /accounts': [200, page([account('110505', 'CAJA GENERAL')])],
      'POST /accounts': [409, {error: 'account_code_taken', message: 'x'}],
    });
    renderChart();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Agregar subcuenta bajo 110505',
      }),
    );
    const dialog = screen.getByRole('dialog');
    await userEvent.type(within(dialog).getByLabelText('Código'), '01');
    await userEvent.type(within(dialog).getByLabelText('Nombre'), 'Caja');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      await within(dialog).findByText('Ya existe una cuenta con ese código.'),
    ).toBeInTheDocument();
  });

  it('keeps the name of a PUC account and deactivates it', async () => {
    const api = fakeApi({
      'GET /me': me('accountant'),
      'GET /accounts': [200, page([account('110510', 'CAJAS MENORES')])],
      'PUT /accounts/id-110510': (body) => [
        200,
        account('110510', 'CAJAS MENORES', body as object),
      ],
    });
    renderChart();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Editar la cuenta 110510'}),
    );
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByLabelText('Nombre')).toBeDisabled();
    await userEvent.click(within(dialog).getByLabelText('Cuenta activa'));
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Guardar'}),
    );

    expect(
      await screen.findByText('Cuenta 110510 guardada.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      name: 'CAJAS MENORES',
      active: false,
      usable_on_purchases: false,
    });
    expect(await screen.findByText('Inactiva')).toBeInTheDocument();
  });

  it('shows a billing user the chart without actions to change it', async () => {
    fakeApi({
      'GET /me': me('billing'),
      'GET /accounts': [200, page([account('110505', 'CAJA GENERAL')])],
    });
    renderChart();

    expect(await screen.findByText('CAJA GENERAL')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Solo el administrador y el contador pueden modificar el plan de cuentas.',
      ),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: /Editar/})).toBeNull();
  });

  it('offers to retry when the chart cannot load', async () => {
    fakeApi({
      'GET /me': me('owner'),
      'GET /accounts': [500, {error: 'internal_error'}],
    });
    renderChart();

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Reintentar'}),
    ).toBeInTheDocument();
  });

  describe('subdividing an account in use', () => {
    const rule = {
      concept: 'ingreso',
      account_id: 'id-413595',
      account_code: '413595',
      account_name: 'VENTA DE OTROS PRODUCTOS',
      allowed_prefixes: ['41'],
      account_postable: true,
    };
    const uses = {
      'GET /posting-rules': [200, {items: [rule]}] as [number, unknown],
      'GET /settings/taxes': [
        200,
        {
          items: [
            {
              id: 't1',
              name: 'IVA 19 %',
              sales_account_id: 'id-240805',
              purchase_account_id: 'id-240810',
            },
          ],
        },
      ] as [number, unknown],
      'GET /settings/payment-methods': [200, {items: []}] as [number, unknown],
    };

    const openAdd = async (code: string) => {
      await userEvent.click(
        await screen.findByRole('button', {
          name: `Agregar subcuenta bajo ${code}`,
        }),
      );
      return screen.getByRole('dialog');
    };

    it('warns that the account stops taking movements and says what moves to the new auxiliar', async () => {
      const api = fakeApi({
        'GET /me': me('accountant'),
        'GET /accounts': [
          200,
          page([account('413595', 'VENTA DE OTROS PRODUCTOS')]),
        ],
        ...uses,
        'POST /accounts': (body) => [
          201,
          {
            ...account('41359501', (body as {name: string}).name),
            moved: {
              posting_rules: ['ingreso'],
              taxes: 0,
              payment_methods: 0,
              products: 2,
              terceros: 0,
            },
          },
        ],
      });
      renderChart();

      const dialog = await openAdd('413595');

      expect(
        await within(dialog).findByText(
          '413595 deja de recibir movimientos: van a sus auxiliares.',
          {exact: false},
        ),
      ).toBeInTheDocument();
      await userEvent.type(within(dialog).getByLabelText('Código'), '01');
      expect(
        within(dialog).getByText(
          'Lo que la usa pasará a 41359501: regla Ingresos por ventas y los productos y terceros que la usen.',
          {exact: false},
        ),
      ).toBeInTheDocument();
      expect(
        within(dialog).getByLabelText('Mover a la nueva auxiliar'),
      ).toBeChecked();
      await userEvent.type(
        within(dialog).getByLabelText('Nombre'),
        'Ventas gravadas',
      );
      await userEvent.click(
        within(dialog).getByRole('button', {name: 'Guardar'}),
      );

      expect(
        await screen.findByText(
          'Cuenta 41359501 creada. Pasaron a 41359501: regla Ingresos por ventas y 2 productos.',
        ),
      ).toBeInTheDocument();
      expect(api.calls.find((c) => c.method === 'POST')?.body).toMatchObject({
        move_references: true,
      });
    });

    it('leaves the references where they are when told not to move them', async () => {
      const api = fakeApi({
        'GET /me': me('owner'),
        'GET /accounts': [
          200,
          page([account('413595', 'VENTA DE OTROS PRODUCTOS')]),
        ],
        ...uses,
        'POST /accounts': [201, account('41359501', 'Ventas gravadas')],
      });
      renderChart();

      const dialog = await openAdd('413595');
      await userEvent.click(
        within(dialog).getByLabelText('Mover a la nueva auxiliar'),
      );
      expect(
        within(dialog).getByText(
          'Sin mover, 413595 queda marcada «Sin auxiliar por defecto» hasta que cambies a mano lo que la usa.',
          {exact: false},
        ),
      ).toBeInTheDocument();
      await userEvent.type(within(dialog).getByLabelText('Código'), '01');
      await userEvent.type(
        within(dialog).getByLabelText('Nombre'),
        'Ventas gravadas',
      );
      await userEvent.click(
        within(dialog).getByRole('button', {name: 'Guardar'}),
      );

      expect(
        await screen.findByText('Cuenta 41359501 creada.'),
      ).toBeInTheDocument();
      expect(api.calls.find((c) => c.method === 'POST')?.body).toMatchObject({
        move_references: false,
      });
    });

    it('does not warn under an account that already has auxiliares', async () => {
      const api = fakeApi({
        'GET /me': me('accountant'),
        'GET /accounts': [
          200,
          page([account('110505', 'CAJA GENERAL', {postable: false})]),
        ],
        ...uses,
        'POST /accounts': [201, account('11050502', 'Caja 2')],
      });
      renderChart();

      const dialog = await openAdd('110505');

      expect(within(dialog).queryByText(/deja de recibir/)).toBeNull();
      expect(
        within(dialog).queryByLabelText('Mover a la nueva auxiliar'),
      ).toBeNull();
      await userEvent.type(within(dialog).getByLabelText('Código'), '02');
      await userEvent.type(within(dialog).getByLabelText('Nombre'), 'Caja 2');
      await userEvent.click(
        within(dialog).getByRole('button', {name: 'Guardar'}),
      );
      await screen.findByText('Cuenta 11050502 creada.');
      expect(
        api.calls.find((c) => c.method === 'POST')?.body,
      ).not.toHaveProperty('move_references');
    });

    it('marks a subdivided account that a rule still points at', async () => {
      fakeApi({
        'GET /me': me('billing'),
        'GET /accounts': [
          200,
          page([
            account('413595', 'VENTA DE OTROS PRODUCTOS', {postable: false}),
            account('41359501', 'Ventas gravadas'),
            account('110505', 'CAJA GENERAL', {postable: false}),
          ]),
        ],
        ...uses,
        'GET /posting-rules': [
          200,
          {items: [{...rule, account_postable: false}]},
        ],
      });
      renderChart();

      const row = (await screen.findByText('413595')).closest('tr');
      expect(
        within(row as HTMLElement).getByText('Sin auxiliar por defecto'),
      ).toBeInTheDocument();
      expect(screen.getAllByText('Sin auxiliar por defecto')).toHaveLength(1);
    });
  });
});
