import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {SignUpForm} from './SignUpForm';

const renderForm = () =>
  render(
    <SessionProvider>
      <SignUpForm />
    </SessionProvider>,
  );

async function fill() {
  await userEvent.type(screen.getByLabelText('Razón social'), 'Acme S.A.S.');
  await userEvent.type(screen.getByLabelText('NIT'), '900123456');
  await userEvent.type(screen.getByLabelText('Tu nombre'), 'Ana Pérez');
  await userEvent.type(
    screen.getByLabelText('Tu correo electrónico'),
    'ana@acme.co',
  );
  await userEvent.type(screen.getByLabelText('Contraseña'), 'una clave larga');
}

describe('signing a company up', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('sends the company and the owner', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/sign-up': [201, {email: 'ana@acme.co'}],
    });
    renderForm();

    await fill();
    await userEvent.click(screen.getByRole('button', {name: 'Crear cuenta'}));

    expect(api.calls.find((c) => c.path === '/auth/sign-up')?.body).toEqual({
      company_name: 'Acme S.A.S.',
      nit: '900123456',
      owner_name: 'Ana Pérez',
      email: 'ana@acme.co',
      password: 'una clave larga',
    });
  });

  it('checks the form before sending it', async () => {
    const api = fakeApi({'GET /me': [401, {error: 'unauthorized'}]});
    renderForm();

    await userEvent.type(screen.getByLabelText('NIT'), 'abc');
    await userEvent.type(screen.getByLabelText('Contraseña'), 'corta');
    await userEvent.click(screen.getByRole('button', {name: 'Crear cuenta'}));

    expect(
      screen.getByText(
        'Escribe el NIT solo con números, sin el dígito de verificación.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText('La contraseña debe tener al menos 10 caracteres.'),
    ).toBeInTheDocument();
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);
  });

  it('shows the server’s reasons on their fields', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/sign-up': [
        422,
        {
          error: 'validation_failed',
          violations: [
            {
              field: 'identification_number',
              message: 'Ya hay una empresa registrada con este NIT.',
            },
          ],
        },
      ],
    });
    renderForm();

    await fill();
    await userEvent.click(screen.getByRole('button', {name: 'Crear cuenta'}));

    expect(
      await screen.findByText('Ya hay una empresa registrada con este NIT.'),
    ).toBeInTheDocument();
  });
});
