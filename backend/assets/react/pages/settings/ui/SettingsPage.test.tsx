import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {MemoryRouter} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {fakeApi} from '@/shared/test/fakeApi';
import {SettingsPage} from './SettingsPage';

describe('Configuración', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('opens the tab named in the address and switches tabs', async () => {
    // The Impuestos tab reads the session (who may edit) and the taxes.
    fakeApi({
      'GET /me': [401, {error: 'unauthorized'}],
      'GET /settings/taxes': [200, {items: []}],
    });
    render(
      <SessionProvider>
        <MemoryRouter initialEntries={['/configuracion?tab=taxes']}>
          <SettingsPage />
        </MemoryRouter>
      </SessionProvider>,
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
