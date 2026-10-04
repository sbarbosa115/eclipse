import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {SignInForm} from './SignInForm';

const renderForm = () =>
  render(
    <SessionProvider>
      <SignInForm />
    </SessionProvider>,
  );

describe('signing in', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('sends the e-mail and password', async () => {
    const api = fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/sign-in': [200, {email: 'ana@acme.co'}],
    });
    renderForm();

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      ' ana@acme.co ',
    );
    await userEvent.type(screen.getByLabelText('Contraseña'), 'secreto123');
    await userEvent.click(screen.getByRole('button', {name: 'Ingresar'}));

    const call = api.calls.find((c) => c.path === '/auth/sign-in');
    expect(call?.body).toEqual({email: 'ana@acme.co', password: 'secreto123'});
  });

  it('says the pair is wrong without saying which part', async () => {
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'POST /auth/sign-in': [401, {error: 'invalid_credentials'}],
    });
    renderForm();

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      'ana@acme.co',
    );
    await userEvent.type(screen.getByLabelText('Contraseña'), 'mala');
    await userEvent.click(screen.getByRole('button', {name: 'Ingresar'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Correo o contraseña incorrectos.',
    );
  });

  it('does not send an empty form', async () => {
    const api = fakeApi({'GET /me': [401, {error: 'unauthorized'}]});
    renderForm();

    await userEvent.click(screen.getByRole('button', {name: 'Ingresar'}));

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Este campo es obligatorio.',
    );
    expect(api.calls.filter((c) => c.method === 'POST')).toHaveLength(0);
  });
});
