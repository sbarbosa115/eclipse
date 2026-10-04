import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {fakeApi} from '@/shared/test/fakeApi';
import {QuickCreateTerceroModal} from './QuickCreateTerceroModal';

const CREATED = {
  id: 't1',
  display_name: 'Cliente Rápido S.A.S.',
  person_type: 'empresa',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  email: 'rapido@cliente.co',
  city: null,
  roles: ['cliente'],
  active: true,
  branch_code: '0',
  trade_name: null,
};

function open(onCreated = vi.fn(), onClose = vi.fn()) {
  render(
    <QuickCreateTerceroModal
      defaultRole="cliente"
      onCreated={onCreated}
      onClose={onClose}
    />,
  );
  return {onCreated, onClose};
}

describe('creating a tercero from a document', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('opens for the role the document needs and shows the DV it will compute', async () => {
    open();

    expect(screen.getByRole('checkbox', {name: 'Cliente'})).toBeChecked();
    expect(screen.getByRole('checkbox', {name: 'Proveedor'})).not.toBeChecked();
    await userEvent.type(
      screen.getByLabelText('Número de identificación'),
      '800197268',
    );
    expect(screen.getByText(/Calculado: 4/)).toBeInTheDocument();
  });

  it('sends the essentials and hands the new tercero back', async () => {
    const api = fakeApi({'POST /terceros/quick': [201, CREATED]});
    const {onCreated} = open();

    await userEvent.type(
      screen.getByLabelText('Número de identificación'),
      '800197268',
    );
    await userEvent.type(
      screen.getByLabelText('Razón social'),
      'Cliente Rápido S.A.S.',
    );
    await userEvent.type(
      screen.getByLabelText('Correo electrónico'),
      'rapido@cliente.co',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Crear tercero'}));

    expect(api.calls[0]?.body).toEqual({
      person_type: 'empresa',
      identification_type: 'nit',
      identification_number: '800197268',
      check_digit: null,
      first_names: null,
      last_names: null,
      business_name: 'Cliente Rápido S.A.S.',
      email: 'rapido@cliente.co',
      roles: ['cliente'],
    });
    expect(onCreated).toHaveBeenCalledWith(CREATED);
  });

  it('asks for what is missing before sending anything', async () => {
    const api = fakeApi({});
    const {onCreated} = open();

    await userEvent.click(screen.getByRole('button', {name: 'Crear tercero'}));

    expect(screen.getAllByText('Este campo es obligatorio.')).toHaveLength(3);
    expect(api.calls).toHaveLength(0);
    expect(onCreated).not.toHaveBeenCalled();
  });

  it('asks for the nombres of a person instead of a razón social', async () => {
    open();

    await userEvent.selectOptions(screen.getByLabelText('Tipo'), 'persona');

    expect(screen.getByLabelText('Nombres')).toBeInTheDocument();
    expect(screen.queryByLabelText('Razón social')).not.toBeInTheDocument();
  });

  it('shows a repeated identification next to its field and keeps the modal open', async () => {
    fakeApi({
      'POST /terceros/quick': [
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
    const {onCreated} = open();

    await userEvent.type(
      screen.getByLabelText('Número de identificación'),
      '800197268',
    );
    await userEvent.type(screen.getByLabelText('Razón social'), 'X');
    await userEvent.type(screen.getByLabelText('Correo electrónico'), 'x@x.co');
    await userEvent.click(screen.getByRole('button', {name: 'Crear tercero'}));

    expect(
      await screen.findByText('Ya existe un tercero con esta identificación.'),
    ).toBeInTheDocument();
    expect(onCreated).not.toHaveBeenCalled();
  });
});
