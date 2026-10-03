import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {App} from './App';

const ANA = {
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana Pérez',
  role: 'billing',
  company_id: 'c1',
  company_name: 'Acme S.A.S.',
  company_nit: '900123456',
  company_check_digit: '8',
};

describe('the app', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    window.history.pushState({}, '', '/');
  });

  it('sends someone signed out to the sign-in page', async () => {
    fakeApi({'GET /me': [401, {error: 'unauthorized'}]});
    window.history.pushState({}, '', '/facturas-venta');
    render(<App />);

    expect(
      await screen.findByRole('heading', {name: 'Ingresa a tu empresa'}),
    ).toBeInTheDocument();
  });

  it('remembers the whole address it was sent away from, query included', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/sign-in': [200, {...ANA, role: 'owner'}],
    });
    window.history.pushState({}, '', '/configuracion?tab=taxes');
    render(<App />);

    await userEvent.type(
      await screen.findByLabelText('Correo electrónico'),
      'ana@acme.co',
    );
    await userEvent.type(screen.getByLabelText('Contraseña'), 'secreto123');
    await userEvent.click(screen.getByRole('button', {name: 'Ingresar'}));

    expect(
      await screen.findByRole('tab', {name: 'Impuestos', selected: true}),
    ).toBeInTheDocument();
    expect(api.calls.some((c) => c.path === '/auth/sign-in')).toBe(true);
  });

  it('shows the company and the menu its role may use', async () => {
    fakeApi({'GET /me': [200, ANA]});
    render(<App />);

    expect(await screen.findAllByText('Acme S.A.S.')).not.toHaveLength(0);
    expect(screen.getByText('NIT 900123456-8')).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Facturas de venta'}),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Configuración'}),
      'A billing user does not configure the company.',
    ).not.toBeInTheDocument();
  });
});
