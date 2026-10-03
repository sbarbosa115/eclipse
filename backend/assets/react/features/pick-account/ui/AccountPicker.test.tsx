import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {fakeApi} from '@/shared/test/fakeApi';
import {Field} from '@/shared/ui';
import {AccountPicker, type AccountChoice} from './AccountPicker';

const IVA = {
  id: 'a1',
  code: '240805',
  name: 'IVA generado',
  postable: true,
};

function Harness({onPick}: {onPick: (choice: AccountChoice) => void}) {
  const [value, setValue] = useState<AccountChoice>({id: null, text: ''});
  return (
    <Field label="Cuenta">
      <AccountPicker
        value={value}
        onChange={(choice) => {
          setValue(choice);
          onPick(choice);
        }}
      />
    </Field>
  );
}

describe('the account picker', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('searches as you type and offers the matches', async () => {
    const api = fakeApi({'GET /accounts/search': [200, {items: [IVA]}]});
    render(<Harness onPick={() => undefined} />);

    await userEvent.type(screen.getByLabelText('Cuenta'), 'iva');

    await waitFor(() =>
      expect(document.querySelector('datalist option')).toHaveAttribute(
        'value',
        '240805 · IVA generado',
      ),
    );
    expect(api.calls.at(-1)?.url.searchParams.get('q')).toBe('iva');
  });

  it('chooses the account when its code is typed, and shows its full label', async () => {
    fakeApi({'GET /accounts/search': [200, {items: [IVA]}]});
    const picks: AccountChoice[] = [];
    render(<Harness onPick={(choice) => picks.push(choice)} />);

    await userEvent.type(screen.getByLabelText('Cuenta'), '240805');

    await waitFor(() =>
      expect(screen.getByLabelText('Cuenta')).toHaveValue(
        '240805 · IVA generado',
      ),
    );
    expect(picks.at(-1)).toEqual({id: 'a1', text: '240805 · IVA generado'});
  });

  it('holds no account while the text matches none', async () => {
    fakeApi({'GET /accounts/search': [200, {items: []}]});
    const picks: AccountChoice[] = [];
    render(<Harness onPick={(choice) => picks.push(choice)} />);

    await userEvent.type(screen.getByLabelText('Cuenta'), 'zzz');

    expect(picks.at(-1)).toEqual({id: null, text: 'zzz'});
  });
});
