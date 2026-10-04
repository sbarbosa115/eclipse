import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {TercerosPage} from './TercerosPage';

const session = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role,
  permissions: permissionsOf(role),
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
});

const summary = (over: Record<string, unknown> = {}) => ({
  id: 't1',
  display_name: 'Distribuciones Andina S.A.S.',
  person_type: 'empresa',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  email: 'facturas@andina.co',
  city: 'Bogotá',
  roles: ['cliente', 'proveedor'],
  active: true,
  branch_code: '0',
  trade_name: null,
  ...over,
});

const page = (items: unknown[], total = items.length) => ({
  items,
  total,
  page: 1,
  per_page: 25,
});

const full = (over: Record<string, unknown> = {}) => ({
  ...summary(),
  branch_code: '0',
  first_names: null,
  last_names: null,
  business_name: 'Distribuciones Andina S.A.S.',
  trade_name: null,
  address: null,
  phones: [],
  billing_contact_name: null,
  mobile: null,
  postal_code: null,
  vat_regime: null,
  billing_contact_is_payer: false,
  fiscal_responsibilities: ['R-99-PN'],
  receivable_account: null,
  payable_account: null,
  contacts: [{id: 'c1', name: 'Pedro Ruiz', email: null, phone: null}],
  erased_at: null,
  created_at: '2026-10-03T10:00:00+00:00',
  ...over,
});

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[`/terceros${path}`]}>
      <SessionProvider>
        <Routes>
          <Route path="terceros/*" element={<TercerosPage />} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );
}

const ACCOUNTS: [number, unknown] = [200, {items: []}];

describe('the terceros list', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists terceros with their identification, roles and actions', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [
        200,
        page([
          summary(),
          summary({
            id: 't2',
            display_name: 'Ana Pérez',
            identification_type: 'cc',
            identification_number: '1020304050',
            check_digit: null,
            roles: ['empleado'],
            active: false,
          }),
        ]),
      ],
    });
    renderAt('/');

    const row = (
      await screen.findByText('Distribuciones Andina S.A.S.')
    ).closest('tr') as HTMLElement;
    expect(within(row).getByText('NIT 800197268-4')).toBeInTheDocument();
    expect(within(row).getByText('Cliente')).toBeInTheDocument();
    expect(within(row).getByText('Proveedor')).toBeInTheDocument();
    expect(
      within(row).getByRole('button', {name: 'Desactivar'}),
    ).toBeInTheDocument();
    const inactive = screen.getByText('Ana Pérez').closest('tr') as HTMLElement;
    expect(within(inactive).getByText('CC 1020304050')).toBeInTheDocument();
    expect(
      within(inactive).getByRole('button', {name: 'Activar'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('columnheader', {name: 'Acciones'}),
    ).toBeInTheDocument();
  });

  it('searches, filters by role and by status through the query string', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([summary()])],
    });
    renderAt('/');
    await screen.findByText('Distribuciones Andina S.A.S.');

    await userEvent.selectOptions(screen.getByLabelText('Rol'), 'proveedor');
    await userEvent.selectOptions(screen.getByLabelText('Estado'), '1');
    await userEvent.type(screen.getByRole('searchbox'), 'andina');

    const last = await vi.waitFor(() => {
      const call = api.calls.filter((c) => c.path === '/terceros').at(-1);
      expect(call?.url.searchParams.get('q')).toBe('andina');
      return call;
    });
    expect(last?.url.searchParams.get('role')).toBe('proveedor');
    expect(last?.url.searchParams.get('active')).toBe('1');
  });

  it('asks for the next page', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([summary()], 60)],
    });
    renderAt('/');
    await screen.findByText('Distribuciones Andina S.A.S.');

    await userEvent.click(screen.getByRole('button', {name: 'Siguiente'}));

    await vi.waitFor(() =>
      expect(
        api.calls
          .filter((c) => c.path === '/terceros')
          .at(-1)
          ?.url.searchParams.get('page'),
      ).toBe('2'),
    );
  });

  it('invites to create the first tercero when there are none', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([])],
    });
    renderAt('/');

    expect(
      await screen.findByText(/Aún no tienes terceros/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Crear tercero'}),
    ).toBeInTheDocument();
  });

  it('offers a way back when a filter leaves nothing', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([])],
    });
    renderAt('/?q=zzz');

    expect(
      await screen.findByText('Ningún tercero coincide con tu búsqueda.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Ver todo'})).toBeInTheDocument();
  });

  it('says so when the list cannot be loaded, and retries', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [500, {error: 'internal_error'}],
    });
    renderAt('/');

    expect(
      await screen.findByText('No pudimos cargar esta información.'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Reintentar'}),
    ).toBeInTheDocument();
  });

  it('shows someone who may only read the list without any way to change it', async () => {
    fakeApi({
      'GET /me': [200, session('reader')],
      'GET /terceros': [200, page([summary()])],
    });
    renderAt('/');

    await screen.findByText('Distribuciones Andina S.A.S.');
    expect(
      screen.queryByRole('link', {name: 'Nuevo tercero'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Desactivar'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Eliminar'}),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Ver'})).toBeInTheDocument();
  });

  it('confirms before deleting, and says why when a document uses the tercero', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([summary()])],
      'DELETE /terceros/t1': [409, {error: 'tercero_in_use'}],
    });
    renderAt('/');
    await screen.findByText('Distribuciones Andina S.A.S.');

    await userEvent.click(screen.getByRole('button', {name: 'Eliminar'}));
    const dialog = screen.getByRole('dialog', {name: 'Eliminar tercero'});
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Eliminar'}),
    );

    expect(
      await within(dialog).findByText(/no se puede eliminar, solo desactivar/),
    ).toBeInTheDocument();
  });

  it('deactivates after confirming and reloads', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /terceros': [200, page([summary()])],
      'POST /terceros/t1/deactivate': [200, full({active: false})],
    });
    renderAt('/');
    await screen.findByText('Distribuciones Andina S.A.S.');

    await userEvent.click(screen.getByRole('button', {name: 'Desactivar'}));
    const dialog = screen.getByRole('dialog', {name: 'Desactivar tercero'});
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Desactivar'}),
    );

    await vi.waitFor(() =>
      expect(api.calls.some((c) => c.path === '/terceros/t1/deactivate')).toBe(
        true,
      ),
    );
    await vi.waitFor(() =>
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
    );
    expect(
      api.calls.filter((c) => c.path === '/terceros').length,
    ).toBeGreaterThan(1);
  });
});

describe('the tercero form page', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('creates a tercero with every group of fields', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
      'GET /terceros': [200, page([])],
      'POST /terceros': [201, full()],
    });
    renderAt('/nuevo');

    await userEvent.type(
      await screen.findByLabelText('Número de identificación'),
      '800197268',
    );
    expect(screen.getByText(/Calculado: 4/)).toBeInTheDocument();
    await userEvent.type(
      screen.getByLabelText('Razón social'),
      'Distribuciones Andina S.A.S.',
    );
    await userEvent.click(screen.getByRole('checkbox', {name: 'Cliente'}));
    await userEvent.click(
      screen.getByRole('button', {name: 'Agregar teléfono'}),
    );
    await userEvent.type(screen.getByLabelText('Número'), '6011234567');
    await userEvent.type(
      screen.getByLabelText(/^Correo electrónico/),
      'facturas@andina.co',
    );
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'O-15 Autorretenedor'}),
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Agregar contacto'}),
    );
    await userEvent.type(
      screen.getByLabelText('Nombre del contacto'),
      'Pedro Ruiz',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Crear tercero'}));

    const body = api.calls.find(
      (c) => c.method === 'POST' && c.path === '/terceros',
    )?.body as Record<string, unknown>;
    expect(body).toMatchObject({
      person_type: 'empresa',
      identification_type: 'nit',
      identification_number: '800197268',
      check_digit: null,
      business_name: 'Distribuciones Andina S.A.S.',
      email: 'facturas@andina.co',
      roles: ['cliente'],
      fiscal_responsibilities: ['R-99-PN', 'O-15'],
      phones: [{indicative: '57', number: '6011234567', extension: null}],
      contacts: [{id: null, name: 'Pedro Ruiz', email: null, phone: null}],
    });
    expect(
      await screen.findByText(/Tercero Distribuciones Andina S.A.S. creado./),
    ).toBeInTheDocument();
  });

  it('checks the form before sending and marks the fields', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
    });
    renderAt('/nuevo');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Crear tercero'}),
    );

    expect(screen.getAllByText('Este campo es obligatorio.')).toHaveLength(2);
    expect(screen.getByText('Elige al menos un rol.')).toBeInTheDocument();
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);
  });

  it('shows a repeated identification on its field', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
      'POST /terceros': [
        422,
        {
          error: 'duplicate_identification',
          violations: [
            {
              field: 'identification_number',
              message: 'Ya existe un tercero con esta identificación.',
            },
          ],
        },
      ],
    });
    renderAt('/nuevo');

    await userEvent.type(
      await screen.findByLabelText('Número de identificación'),
      '800197268',
    );
    await userEvent.type(screen.getByLabelText('Razón social'), 'X');
    await userEvent.click(screen.getByRole('checkbox', {name: 'Cliente'}));
    await userEvent.click(screen.getByRole('button', {name: 'Crear tercero'}));

    expect(
      await screen.findByText('Ya existe un tercero con esta identificación.'),
    ).toBeInTheDocument();
  });

  it('opens an existing tercero, keeps its contact id and saves', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
      'GET /terceros/t1': [200, full()],
      'PUT /terceros/t1': [200, full({city: 'Cali'})],
    });
    renderAt('/t1');

    const city = await screen.findByLabelText(/Ciudad/);
    expect(screen.getByDisplayValue('Pedro Ruiz')).toBeInTheDocument();
    await userEvent.clear(city);
    await userEvent.type(city, 'Cali');
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar tercero'}),
    );

    expect(await screen.findByText('Cambios guardados.')).toBeInTheDocument();
    const body = api.calls.find((c) => c.method === 'PUT')?.body as {
      city: string;
      contacts: {id: string}[];
    };
    expect(body.city).toBe('Cali');
    expect(body.contacts[0]?.id).toBe('c1');
  });

  it('lets someone who may only read read a tercero but not change it', async () => {
    fakeApi({
      'GET /me': [200, session('reader')],
      'GET /accounts/search': ACCOUNTS,
      'GET /terceros/t1': [200, full()],
    });
    renderAt('/t1');

    expect(
      await screen.findByDisplayValue('Distribuciones Andina S.A.S.'),
    ).toBeDisabled();
    expect(
      screen.queryByRole('button', {name: 'Guardar tercero'}),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Suprimir datos personales'}),
    ).not.toBeInTheDocument();
  });

  it('erases personal data after confirming and then is read-only', async () => {
    const api = fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
      'GET /terceros/t1': [200, full()],
      'POST /terceros/t1/erase': [
        200,
        full({
          display_name: 'Datos suprimidos',
          business_name: null,
          email: null,
          contacts: [],
          active: false,
          erased_at: '2026-10-03T12:00:00+00:00',
        }),
      ],
    });
    renderAt('/t1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Suprimir datos personales'}),
    );
    const dialog = screen.getByRole('dialog', {
      name: 'Suprimir datos personales',
    });
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Suprimir datos'}),
    );

    expect(
      await screen.findByText(/fueron suprimidos el 03\/10\/2026/),
    ).toBeInTheDocument();
    expect(api.calls.some((c) => c.path === '/terceros/t1/erase')).toBe(true);
    expect(
      screen.queryByRole('button', {name: 'Guardar tercero'}),
    ).not.toBeInTheDocument();
  });

  it('downloads the personal data as JSON', async () => {
    fakeApi({
      'GET /me': [200, session('owner')],
      'GET /accounts/search': ACCOUNTS,
      'GET /terceros/t1': [200, full()],
      'GET /terceros/t1/export': [
        200,
        {exported_at: '2026-10-03T12:00:00+00:00', tercero: full()},
      ],
    });
    const create = vi.fn(() => 'blob:x');
    vi.stubGlobal(
      'URL',
      Object.assign(URL, {createObjectURL: create, revokeObjectURL: vi.fn()}),
    );
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => undefined);
    renderAt('/t1');

    await userEvent.click(
      await screen.findByRole('button', {name: 'Exportar datos (JSON)'}),
    );

    await vi.waitFor(() => expect(create).toHaveBeenCalled());
    expect(click).toHaveBeenCalled();
  });
});
