import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter} from 'react-router-dom';
import {SettingsPage} from './SettingsPage';

describe('Configuración', () => {
  it('opens the tab named in the address and switches tabs', async () => {
    render(
      <MemoryRouter initialEntries={['/configuracion?tab=taxes']}>
        <SettingsPage />
      </MemoryRouter>,
    );

    expect(screen.getByRole('tab', {name: 'Impuestos'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    await userEvent.click(screen.getByRole('tab', {name: 'Usuarios'}));
    expect(screen.getByRole('tab', {name: 'Usuarios'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });
});
