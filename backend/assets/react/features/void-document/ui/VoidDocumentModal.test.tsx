import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {VoidDocumentModal} from './VoidDocumentModal';

function renderModal(onVoid = vi.fn(async (_reason: string) => {})) {
  const onClose = vi.fn();
  render(
    <VoidDocumentModal
      title="Anular factura FE-1"
      body="Se registrará un comprobante de reversión."
      reasonLabel="Motivo"
      reasonRequired="Escribe el motivo."
      confirmLabel="Anular"
      onVoid={onVoid}
      onClose={onClose}
    />,
  );
  return {onVoid, onClose};
}

describe('voiding a document', () => {
  it('asks for the reason before voiding', async () => {
    const {onVoid} = renderModal();

    await userEvent.click(screen.getByRole('button', {name: 'Anular'}));

    expect(screen.getByText('Escribe el motivo.')).toBeInTheDocument();
    expect(onVoid).not.toHaveBeenCalled();
  });

  it('voids with the reason typed', async () => {
    const {onVoid} = renderModal();

    await userEvent.type(
      screen.getByLabelText('Motivo'),
      '  Precio equivocado ',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Anular'}));

    expect(onVoid).toHaveBeenCalledWith('Precio equivocado');
  });

  it('shows why it failed and stays open', async () => {
    renderModal(
      vi.fn(async () => {
        throw new Error('Tiene recibos aplicados.');
      }),
    );

    await userEvent.type(screen.getByLabelText('Motivo'), 'Error');
    await userEvent.click(screen.getByRole('button', {name: 'Anular'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Tiene recibos aplicados.',
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('closes on Cancelar', async () => {
    const {onClose} = renderModal();

    await userEvent.click(screen.getByRole('button', {name: 'Cancelar'}));

    expect(onClose).toHaveBeenCalled();
  });
});
