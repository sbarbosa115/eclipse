import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {AcceptInvitationPage} from './AcceptInvitationPage';

const SESSION = {
  user_id: 'u2',
  email: 'eva@contadores.co',
  name: 'Eva Ruiz',
  role: 'accountant',
  permissions: [
    'MANAGE_BOOKS',
    'VIEW_BOOKS',
    'WRITE_DOCUMENTS',
    'READ_DOCUMENTS',
  ],
  company_id: 'c1',
  company_name: 'Acme S.A.S.',
  company_nit: '900123456',
  company_check_digit: '8',
};

const renderAt = (url: string) =>
  render(
    <MemoryRouter initialEntries={[url]}>
      <SessionProvider>
        <Routes>
          <Route path="invitacion" element={<AcceptInvitationPage />} />
          <Route path="/" element={<p>Tablero</p>} />
          <Route path="ingresar" element={<p>Ingresar</p>} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );

describe('Aceptar invitación', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('shows who invited whom, and accepting lands signed in', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/invitations/lookup': [
        200,
        {
          email: 'eva@contadores.co',
          company_name: 'Acme S.A.S.',
          role: 'accountant',
          permissions: [
            'MANAGE_BOOKS',
            'VIEW_BOOKS',
            'WRITE_DOCUMENTS',
            'READ_DOCUMENTS',
          ],
        },
      ],
      'POST /auth/invitations/accept': [200, SESSION],
    });
    renderAt('/invitacion#tok123');

    expect(
      await screen.findByRole('heading', {name: 'Únete a Acme S.A.S.'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        /Te invitaron como Contador con el correo eva@contadores.co/,
      ),
    ).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Tu nombre'), 'Eva Ruiz');
    await userEvent.type(
      screen.getByLabelText('Contraseña'),
      'una clave bien larga',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Entrar a la empresa'}),
    );

    expect(await screen.findByText('Tablero')).toBeInTheDocument();
    expect(
      api.calls.find((c) => c.path === '/auth/invitations/lookup')?.body,
    ).toEqual({token: 'tok123'});
    expect(
      api.calls.find((c) => c.path === '/auth/invitations/accept')?.body,
    ).toEqual({
      token: 'tok123',
      name: 'Eva Ruiz',
      password: 'una clave bien larga',
    });
  });

  it('checks the name and the password length before sending', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/invitations/lookup': [
        200,
        {email: 'eva@contadores.co', company_name: 'Acme', role: 'billing'},
      ],
    });
    renderAt('/invitacion#tok123');

    await userEvent.type(await screen.findByLabelText('Contraseña'), 'corta');
    await userEvent.click(
      screen.getByRole('button', {name: 'Entrar a la empresa'}),
    );

    expect(screen.getByText('Este campo es obligatorio.')).toBeInTheDocument();
    expect(
      screen.getByText('La contraseña debe tener al menos 10 caracteres.'),
    ).toBeInTheDocument();
    expect(
      api.calls.filter((c) => c.path === '/auth/invitations/accept'),
    ).toHaveLength(0);
  });

  it('says so when the link no longer works', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/invitations/lookup': [404, {error: 'link_invalid'}],
    });
    renderAt('/invitacion#viejo');

    expect(
      await screen.findByRole('heading', {name: 'Este enlace ya no sirve'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Ir a ingresar'})).toHaveAttribute(
      'href',
      '/ingresar',
    );
  });

  it('without a token in the address, the link is invalid at once', async () => {
    const api = fakeApi({'GET /me': [401, {error: 'unauthorized'}]});
    renderAt('/invitacion');

    expect(
      await screen.findByRole('heading', {name: 'Este enlace ya no sirve'}),
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(api.calls.map((c) => c.path)).not.toContain(
        '/auth/invitations/lookup',
      ),
    );
  });
});
