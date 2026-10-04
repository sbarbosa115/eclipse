import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {ResetPasswordPage} from './ResetPasswordPage';

const SESSION = {
  user_id: 'u1',
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
          <Route
            path="restablecer-contrasena"
            element={<ResetPasswordPage />}
          />
          <Route path="/" element={<p>Tablero</p>} />
          <Route path="recuperar-contrasena" element={<p>Pedir enlace</p>} />
        </Routes>
      </SessionProvider>
    </MemoryRouter>,
  );

describe('Nueva contraseña', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('sets the new password and lands signed in', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/password-reset/check': [204],
      'POST /auth/password-reset/confirm': [200, SESSION],
    });
    renderAt('/restablecer-contrasena#tok9');

    await userEvent.type(
      await screen.findByLabelText('Contraseña nueva'),
      'otra clave bien larga',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar y entrar'}),
    );

    expect(await screen.findByText('Tablero')).toBeInTheDocument();
    expect(
      api.calls.find((c) => c.path === '/auth/password-reset/confirm')?.body,
    ).toEqual({token: 'tok9', password: 'otra clave bien larga'});
  });

  it('needs at least ten characters', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/password-reset/check': [204],
    });
    renderAt('/restablecer-contrasena#tok9');

    await userEvent.type(
      await screen.findByLabelText('Contraseña nueva'),
      'corta',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar y entrar'}),
    );

    expect(
      screen.getByText('La contraseña debe tener al menos 10 caracteres.'),
    ).toBeInTheDocument();
    expect(
      api.calls.filter((c) => c.path === '/auth/password-reset/confirm'),
    ).toHaveLength(0);
  });

  it('offers a new link when this one no longer works', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/password-reset/check': [404, {error: 'link_invalid'}],
    });
    renderAt('/restablecer-contrasena#viejo');

    expect(
      await screen.findByRole('heading', {name: 'Este enlace ya no sirve'}),
    ).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('link', {name: 'Pedir otro enlace'}),
    );
    expect(await screen.findByText('Pedir enlace')).toBeInTheDocument();
  });

  it('says so when the link expires while the page is open', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/password-reset/check': [204],
      'POST /auth/password-reset/confirm': [404, {error: 'link_invalid'}],
    });
    renderAt('/restablecer-contrasena#tok9');

    await userEvent.type(
      await screen.findByLabelText('Contraseña nueva'),
      'otra clave bien larga',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Guardar y entrar'}),
    );

    expect(
      await screen.findByRole('heading', {name: 'Este enlace ya no sirve'}),
    ).toBeInTheDocument();
  });
});
