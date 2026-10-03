import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter} from 'react-router-dom';
import {fakeApi} from '@/shared/test/fakeApi';
import {ForgotPasswordPage} from './ForgotPasswordPage';

const renderPage = () =>
  render(
    <MemoryRouter initialEntries={['/recuperar-contrasena']}>
      <ForgotPasswordPage />
    </MemoryRouter>,
  );

describe('¿Olvidaste tu contraseña?', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('asks for a link and says the same thing whoever asks', async () => {
    const api = fakeApi({'POST /auth/password-reset': [202]});
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      ' ana@acme.co ',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Enviar enlace'}));

    expect(
      await screen.findByText(
        'Si ana@acme.co tiene una cuenta en Mustang, te llegará un correo con un enlace. El enlace vence en una hora.',
      ),
    ).toBeInTheDocument();
    expect(api.calls[0]?.body).toEqual({email: 'ana@acme.co'});
    expect(
      screen.getByRole('link', {name: 'Volver a ingresar'}),
    ).toHaveAttribute('href', '/ingresar');
  });

  it('checks the e-mail first', async () => {
    const api = fakeApi({});
    renderPage();

    await userEvent.type(screen.getByLabelText('Correo electrónico'), 'ana');
    await userEvent.click(screen.getByRole('button', {name: 'Enviar enlace'}));

    expect(screen.getByText('Escribe un correo válido.')).toBeInTheDocument();
    expect(api.calls).toHaveLength(0);
  });

  it('asks to wait after too many requests', async () => {
    fakeApi({'POST /auth/password-reset': [429, {error: 'too_many_requests'}]});
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      'ana@acme.co',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Enviar enlace'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Demasiados intentos. Espera un momento e inténtalo de nuevo.',
    );
  });
});
