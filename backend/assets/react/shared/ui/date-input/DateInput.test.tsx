import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {DateInput} from './DateInput';

function Probe({initial = ''}: {initial?: string}) {
  const [value, setValue] = useState(initial);
  return (
    <>
      <label htmlFor="d">Fecha</label>
      <DateInput id="d" value={value} onChange={setValue} />
      <output>{value === '' ? 'vacía' : value}</output>
    </>
  );
}

describe('DateInput', () => {
  it('shows an ISO date as DD/MM/YYYY', () => {
    render(<Probe initial="2026-10-03" />);

    expect(screen.getByLabelText('Fecha')).toHaveValue('03/10/2026');
  });

  it('reads what a Colombian types, day first, and hands back ISO', async () => {
    render(<Probe />);

    await userEvent.type(screen.getByLabelText('Fecha'), '3/10/2026');

    expect(screen.getByRole('status')).toHaveTextContent('2026-10-03');
  });

  it('gives nothing for a date that does not exist, and keeps what was typed', async () => {
    render(<Probe />);

    await userEvent.type(screen.getByLabelText('Fecha'), '31/02/2026');

    expect(screen.getByRole('status')).toHaveTextContent('vacía');
    expect(screen.getByLabelText('Fecha')).toHaveValue('31/02/2026');
  });

  it('writes the full form when the field is left', async () => {
    render(<Probe />);

    await userEvent.type(screen.getByLabelText('Fecha'), '3/1/2026');
    await userEvent.tab();

    expect(screen.getByLabelText('Fecha')).toHaveValue('03/01/2026');
  });
});
