import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {MoneyInput} from './MoneyInput';

function Probe({initial = '', places}: {initial?: string; places?: 2 | 4}) {
  const [value, setValue] = useState(initial);
  return (
    <>
      <label htmlFor="m">Valor</label>
      <MoneyInput id="m" value={value} onChange={setValue} places={places} />
      <output>{value === '' ? 'vacío' : value}</output>
      <button type="button" onClick={() => setValue('250000.00')}>
        Poner
      </button>
    </>
  );
}

describe('MoneyInput', () => {
  it('shows the decimal it is given as a Colombian writes it', () => {
    render(<Probe initial="1190000.50" />);

    expect(screen.getByLabelText('Valor')).toHaveValue('1.190.000,50');
  });

  it.each([
    ['1.190.000,50', '1190000.50'],
    ['595000,5', '595000.5'],
    ['595000.50', '595000.50'],
  ])(
    'reads %s and hands the form %s, a decimal with a point',
    async (typed, value) => {
      render(<Probe />);

      await userEvent.type(screen.getByLabelText('Valor'), typed);

      expect(screen.getByRole('status')).toHaveTextContent(value);
    },
  );

  it('keeps what is typed while typing and tidies it when the field is left', async () => {
    render(<Probe />);
    const input = screen.getByLabelText('Valor');

    await userEvent.type(input, '1190000');
    expect(input).toHaveValue('1190000');
    await userEvent.tab();

    expect(input).toHaveValue('1.190.000');
    expect(screen.getByRole('status')).toHaveTextContent('1190000');
  });

  it('takes up to four decimals for a unit price, two for money', async () => {
    render(<Probe places={4} />);

    await userEvent.type(screen.getByLabelText('Valor'), '42016,8067');

    expect(screen.getByRole('status')).toHaveTextContent('42016.8067');
  });

  it('hands back what is typed when it is not a number, so the form can say so', async () => {
    render(<Probe />);

    await userEvent.type(screen.getByLabelText('Valor'), '12,345');

    expect(screen.getByRole('status')).toHaveTextContent('12,345');
    expect(screen.getByLabelText('Valor')).toHaveValue('12,345');
  });

  it('follows a value set from outside', async () => {
    render(<Probe initial="10" />);

    await userEvent.click(screen.getByRole('button', {name: 'Poner'}));

    expect(screen.getByLabelText('Valor')).toHaveValue('250.000');
  });

  it('hands back nothing when it is emptied', async () => {
    render(<Probe initial="10" />);

    await userEvent.clear(screen.getByLabelText('Valor'));

    expect(screen.getByRole('status')).toHaveTextContent('vacío');
  });
});
