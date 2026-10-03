import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {App} from './App';

// The "access" item's part of the app: the public pages an e-mailed link opens, and a session that ends while the
// app is open (§4.14, two hours without a request) going back to the sign-in page.

const ANA = {
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana Pérez',
  role: 'owner',
  company_id: 'c1',
  company_name: 'Acme S.A.S.',
  company_nit: '900123456',
  company_check_digit: '8',
};

describe('access in the app', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    window.history.pushState({}, '', '/');
  });

  it('the sign-in page links to "¿Olvidaste tu contraseña?"', async () => {
    fakeApi({'GET /me': [401, {error: 'unauthorized'}]});
    window.history.pushState({}, '', '/ingresar');
    render(<App />);

    await userEvent.click(
      await screen.findByRole('link', {name: '¿Olvidaste tu contraseña?'}),
    );

    expect(
      await screen.findByRole('heading', {name: '¿Olvidaste tu contraseña?'}),
    ).toBeInTheDocument();
  });

  it('opens the invitation page signed out', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/invitations/lookup': [
        200,
        {email: 'luis@acme.co', company_name: 'Acme S.A.S.', role: 'billing'},
      ],
    });
    window.history.pushState({}, '', '/invitacion#tok');
    render(<App />);

    expect(
      await screen.findByRole('heading', {name: 'Únete a Acme S.A.S.'}),
    ).toBeInTheDocument();
    expect(window.location.hash, 'The token leaves the address bar.').toBe('');
  });

  it('opens the new-password page signed out', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/password-reset/check': [204],
    });
    window.history.pushState({}, '', '/restablecer-contrasena#tok');
    render(<App />);

    expect(
      await screen.findByRole('heading', {name: 'Elige una contraseña nueva'}),
    ).toBeInTheDocument();
  });

  it('a session that expired while the app was open shows the sign-in page', async () => {
    fakeApi({
      'GET /me': [200, ANA],
      'GET /users': [401, {error: 'session_expired', message: 'x'}],
    });
    window.history.pushState({}, '', '/configuracion?tab=users');
    render(<App />);

    // Configuración is a lazy chunk: on a busy machine it takes longer than the usual wait to load and call.
    expect(
      await screen.findByRole(
        'heading',
        {name: 'Ingresa a tu empresa'},
        {timeout: 10_000},
      ),
    ).toBeInTheDocument();
  });
});
