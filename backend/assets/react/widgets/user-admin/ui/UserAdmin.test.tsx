import {permissionsOf} from '@/shared/test/permissions';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {UserAdmin} from './UserAdmin';

const sessionOf = (role: string) => ({
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana Pérez',
  role,
  permissions: permissionsOf(role),
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
});

const user = (over: Record<string, unknown> = {}) => ({
  id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana Pérez',
  role: 'owner',
  permissions: [
    'MANAGE_USERS',
    'MANAGE_SETTINGS',
    'MANAGE_BOOKS',
    'VIEW_BOOKS',
    'WRITE_DOCUMENTS',
    'READ_DOCUMENTS',
  ],
  status: 'active',
  is_you: true,
  created_at: '2026-10-01T10:00:00+00:00',
  last_sign_in_at: '2026-10-03T15:30:00+00:00',
  invitation_expires_at: null,
  ...over,
});

const LUIS = user({
  id: 'u2',
  email: 'luis@acme.co',
  name: 'Luis Gómez',
  role: 'billing',
  is_you: false,
  last_sign_in_at: null,
});
const EVA = user({
  id: 'u3',
  email: 'eva@contadores.co',
  name: '',
  role: 'accountant',
  status: 'invited',
  is_you: false,
  last_sign_in_at: null,
  invitation_expires_at: '2099-10-10T10:00:00+00:00',
});

const rowOf = (text: string): HTMLElement => {
  const row = screen
    .getAllByRole('row')
    .find((r) => within(r).queryAllByRole('cell')[1]?.textContent === text);
  if (!row) throw new Error(`No row for ${text}`);
  return row;
};

const renderTab = () =>
  render(
    <SessionProvider>
      <UserAdmin />
    </SessionProvider>,
  );

describe('Configuración › Usuarios', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('lists the users with their role, status and last sign-in', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': [200, {items: [user(), EVA, LUIS]}],
    });
    renderTab();

    await screen.findByText('Luis Gómez');
    expect(rowOf('ana@acme.co')).toHaveTextContent('Tú');
    expect(rowOf('ana@acme.co')).toHaveTextContent('Administrador');
    expect(rowOf('luis@acme.co')).toHaveTextContent('Facturación');
    expect(rowOf('luis@acme.co')).toHaveTextContent('Nunca');
    expect(rowOf('eva@contadores.co')).toHaveTextContent('Sin nombre aún');
    expect(rowOf('eva@contadores.co')).toHaveTextContent(
      'Invitación vigente hasta el',
    );
    expect(rowOf('eva@contadores.co')).toHaveTextContent('Invitado');
  });

  it('tells anyone but the owner that only the owner manages users', async () => {
    const api = fakeApi({'GET /me': [200, sessionOf('accountant')]});
    renderTab();

    expect(
      await screen.findByText(
        'Solo el administrador de la empresa gestiona los usuarios.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.map((c) => c.path)).not.toContain('/users');
  });

  it('invites a user and lists them', async () => {
    let listed = [user()];
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': () => [200, {items: listed}],
      'POST /users/invitations': () => {
        listed = [user(), EVA];
        return [201, EVA];
      },
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Invitar a tu contador'}),
    );
    const dialog = screen.getByRole('dialog', {name: 'Invitar a tu contador'});
    await userEvent.type(
      within(dialog).getByLabelText('Correo electrónico'),
      'eva@contadores.co',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Enviar invitación'}),
    );

    expect(
      await screen.findByText('Enviamos la invitación a eva@contadores.co.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(await screen.findByText('eva@contadores.co')).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'POST')?.body).toEqual({
      email: 'eva@contadores.co',
      role: 'accountant',
    });
  });

  it('changes a role', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': [200, {items: [user(), LUIS]}],
      'PUT /users/u2/role': [200, {...LUIS, role: 'accountant'}],
    });
    renderTab();
    await screen.findByText('Luis Gómez');

    await userEvent.click(
      within(rowOf('luis@acme.co')).getByRole('button', {name: 'Cambiar rol'}),
    );
    const dialog = screen.getByRole('dialog', {
      name: 'Cambiar el rol de Luis Gómez',
    });
    expect(within(dialog).getByLabelText('Rol')).toHaveValue('billing');
    await userEvent.selectOptions(
      within(dialog).getByLabelText('Rol'),
      'accountant',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Cambiar rol'}),
    );

    expect(
      await screen.findByText('Luis Gómez ahora tiene el rol Contador.'),
    ).toBeInTheDocument();
    expect(api.calls.find((c) => c.method === 'PUT')?.body).toEqual({
      role: 'accountant',
    });
  });

  it('explains that the last owner keeps the role', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': [200, {items: [user()]}],
      'PUT /users/u1/role': [409, {error: 'last_owner', message: 'x'}],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Cambiar rol'}),
    );
    const dialog = screen.getByRole('dialog');
    await userEvent.selectOptions(
      within(dialog).getByLabelText('Rol'),
      'billing',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Cambiar rol'}),
    );

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'La empresa debe tener al menos un administrador activo.',
    );
  });

  it('deactivates after confirming, and reactivates', async () => {
    let luis = LUIS;
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': () => [200, {items: [user(), luis]}],
      'POST /users/u2/deactivate': () => {
        luis = {...LUIS, status: 'deactivated'};
        return [200, luis];
      },
      'POST /users/u2/reactivate': () => {
        luis = LUIS;
        return [200, luis];
      },
    });
    renderTab();
    await screen.findByText('Luis Gómez');

    expect(
      within(rowOf('ana@acme.co')).queryByRole('button', {name: 'Desactivar'}),
    ).not.toBeInTheDocument();
    await userEvent.click(
      within(rowOf('luis@acme.co')).getByRole('button', {name: 'Desactivar'}),
    );
    const dialog = screen.getByRole('dialog', {
      name: 'Desactivar a Luis Gómez',
    });
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Desactivar'}),
    );
    expect(
      await screen.findByText(
        'Luis Gómez ya no puede ingresar. Su sesión se cerró.',
      ),
    ).toBeInTheDocument();

    await userEvent.click(
      await within(rowOf('luis@acme.co')).findByRole('button', {
        name: 'Reactivar',
      }),
    );
    expect(
      await screen.findByText('Luis Gómez puede ingresar de nuevo.'),
    ).toBeInTheDocument();
  });

  it('resends an invitation', async () => {
    const api = fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': [200, {items: [user(), EVA]}],
      'POST /users/u3/invitation': [200, EVA],
    });
    renderTab();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Reenviar invitación'}),
    );

    expect(
      await screen.findByText(
        'Enviamos de nuevo la invitación a eva@contadores.co. El enlace anterior ya no sirve.',
      ),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(1);
  });

  it('filters by status, and "Ver todo" brings everyone back', async () => {
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': [200, {items: [user(), LUIS]}],
    });
    renderTab();
    await screen.findByText('Luis Gómez');

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'invited');
    expect(
      screen.getByText('Ningún usuario coincide con el filtro.'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Ver todo'}));

    expect(screen.getByText('Luis Gómez')).toBeInTheDocument();
    expect(screen.getByLabelText('Estado')).toHaveValue('all');
  });

  it('offers to retry when the list does not load', async () => {
    let fail = true;
    fakeApi({
      'GET /me': [200, sessionOf('owner')],
      'GET /users': () =>
        fail ? [500, {error: 'internal_error'}] : [200, {items: [user()]}],
    });
    renderTab();

    const retry = await screen.findByRole('button', {name: 'Reintentar'});
    fail = false;
    await userEvent.click(retry);

    expect(await screen.findByText('Ana Pérez')).toBeInTheDocument();
  });
});
