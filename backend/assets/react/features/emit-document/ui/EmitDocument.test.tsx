import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {EmitDocument} from './EmitDocument';

function renderEmit(props: Partial<Parameters<typeof EmitDocument>[0]> = {}) {
  const onEmit = vi.fn(async () => {});
  render(
    <EmitDocument
      emitLabel="Emitir"
      emitAndSendLabel="Emitir y enviar"
      confirmTitle="¿Emitir?"
      confirmBody="Tomará su número y no se podrá editar."
      sendNote="Se enviará al cliente."
      onEmit={onEmit}
      {...props}
    />,
  );
  return {onEmit};
}

describe('emitting a document', () => {
  it('asks before emitting, then emits', async () => {
    const {onEmit} = renderEmit();

    await userEvent.click(screen.getByRole('button', {name: 'Emitir'}));
    const dialog = screen.getByRole('dialog', {name: '¿Emitir?'});
    expect(dialog).toHaveTextContent('Tomará su número');
    expect(dialog).not.toHaveTextContent('Se enviará al cliente.');
    await userEvent.click(
      screen.getAllByRole('button', {name: 'Emitir'}).at(-1)!,
    );

    expect(onEmit).toHaveBeenCalledWith(false);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('emits and sends, saying so', async () => {
    const {onEmit} = renderEmit();

    await userEvent.click(
      screen.getByRole('button', {name: 'Emitir y enviar'}),
    );
    expect(screen.getByRole('dialog')).toHaveTextContent(
      'Se enviará al cliente.',
    );
    await userEvent.click(
      screen.getAllByRole('button', {name: 'Emitir y enviar'}).at(-1)!,
    );

    expect(onEmit).toHaveBeenCalledWith(true);
  });

  it('keeps the dialog open with the reason it failed', async () => {
    renderEmit({
      onEmit: vi.fn(async () => {
        throw new Error('La resolución se agotó.');
      }),
    });

    await userEvent.click(screen.getByRole('button', {name: 'Emitir'}));
    await userEvent.click(
      screen.getAllByRole('button', {name: 'Emitir'}).at(-1)!,
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'La resolución se agotó.',
    );
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not ask while the page says the document is not ready', async () => {
    const beforeConfirm = vi.fn(() => false);
    renderEmit({beforeConfirm, emitAndSendLabel: undefined});

    expect(
      screen.queryByRole('button', {name: 'Emitir y enviar'}),
    ).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Emitir'}));

    expect(beforeConfirm).toHaveBeenCalledWith(false);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
