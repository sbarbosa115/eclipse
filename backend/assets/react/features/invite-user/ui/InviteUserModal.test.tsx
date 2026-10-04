import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {InviteUserModal} from './InviteUserModal';

const invited = {
  id: 'u2',
  email: 'luis@acme.co',
  name: '',
  role: 'billing',
  status: 'invited',
  is_you: false,
  created_at: '2026-10-03T10:00:00+00:00',
  last_sign_in_at: null,
  invitation_expires_at: '2026-10-10T10:00:00+00:00',
};

describe('Invitar usuario', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('invites by e-mail with the role chosen', async () => {
    const api = fakeApi({'POST /users/invitations': [201, invited]});
    const onInvited = vi.fn();
    render(<InviteUserModal onClose={() => {}} onInvited={onInvited} />);
    const dialog = screen.getByRole('dialog', {name: 'Invitar usuario'});

    expect(within(dialog).getByLabelText('Rol')).toHaveValue('billing');
    await userEvent.type(
      within(dialog).getByLabelText('Correo electrónico'),
      ' luis@acme.co ',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Enviar invitación'}),
    );

    await waitFor(() => expect(onInvited).toHaveBeenCalledWith(invited));
    expect(api.calls[0]?.body).toEqual({
      email: 'luis@acme.co',
      role: 'billing',
    });
  });

  it('"Invitar a tu contador" opens with the accountant role', async () => {
    const api = fakeApi({
      'POST /users/invitations': [201, {...invited, role: 'accountant'}],
    });
    render(
      <InviteUserModal
        defaultRole="accountant"
        onClose={() => {}}
        onInvited={() => {}}
      />,
    );
    const dialog = screen.getByRole('dialog', {name: 'Invitar a tu contador'});

    expect(within(dialog).getByLabelText('Rol')).toHaveValue('accountant');
    expect(
      within(dialog).getByText(/Plan de cuentas, impuestos/),
    ).toBeInTheDocument();
    await userEvent.type(
      within(dialog).getByLabelText('Correo electrónico'),
      'contador@firma.co',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Enviar invitación'}),
    );
    await waitFor(() => expect(api.calls).toHaveLength(1));
    expect(api.calls[0]?.body).toEqual({
      email: 'contador@firma.co',
      role: 'accountant',
    });
  });

  it('checks the e-mail before sending anything', async () => {
    const api = fakeApi({});
    render(<InviteUserModal onClose={() => {}} onInvited={() => {}} />);

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      'no-es-un-correo',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Enviar invitación'}),
    );

    expect(screen.getByText('Escribe un correo válido.')).toBeInTheDocument();
    expect(api.calls).toHaveLength(0);
  });

  it('shows the server saying the e-mail is already registered under the field', async () => {
    fakeApi({
      'POST /users/invitations': [
        422,
        {
          error: 'validation_failed',
          message: 'Validation error',
          violations: [
            {field: 'email', message: 'Este correo ya está registrado.'},
          ],
        },
      ],
    });
    const onInvited = vi.fn();
    render(<InviteUserModal onClose={() => {}} onInvited={onInvited} />);

    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      'ana@acme.co',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Enviar invitación'}),
    );

    expect(
      await screen.findByText('Este correo ya está registrado.'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Correo electrónico')).toHaveAttribute(
      'aria-invalid',
      'true',
    );
    expect(onInvited).not.toHaveBeenCalled();
  });
});
