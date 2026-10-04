import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import type {OpenItem} from '../model/allocation';
import {AllocatePayment} from './AllocatePayment';

const ITEMS: OpenItem[] = [
  {
    id: 'r1',
    document: 'FE-1',
    issueDate: '2026-09-01',
    dueDate: '2026-10-01',
    amount: '595000.00',
    balance: '595000.00',
  },
  {
    id: 'r2',
    document: 'FE-2',
    issueDate: '2026-09-15',
    dueDate: '2026-10-15',
    amount: '1190000.00',
    balance: '300000.00',
  },
];

function Harness({
  total,
  errors,
  onAmounts = () => {},
}: {
  total: string;
  errors?: Record<string, string>;
  onAmounts?: (amounts: Record<string, string>) => void;
}) {
  const [amounts, setAmounts] = useState<Record<string, string>>({});
  return (
    <AllocatePayment
      items={ITEMS}
      amounts={amounts}
      total={total}
      errors={errors}
      onChange={(next) => {
        setAmounts(next);
        onAmounts(next);
      }}
    />
  );
}

describe('allocating a payment to open items', () => {
  it('lists each open item with its dates, value and balance, oldest first as given', () => {
    render(<Harness total="" />);

    const rows = screen.getAllByRole('row').slice(1);
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent('FE-1');
    expect(rows[0]).toHaveTextContent('01/09/2026');
    expect(rows[0]).toHaveTextContent('01/10/2026');
    expect(rows[1]).toHaveTextContent('1.190.000');
    expect(rows[1]).toHaveTextContent('300.000');
  });

  it('shows the running difference until it is zero', async () => {
    render(<Harness total="895000" />);

    expect(screen.getByRole('status')).toHaveTextContent(
      'Falta aplicar $ 895.000,00',
    );
    await userEvent.type(
      screen.getByLabelText('Valor a aplicar a FE-1'),
      '595.000',
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'Falta aplicar $ 300.000,00',
    );

    await userEvent.click(
      screen.getByRole('button', {name: 'Pagar todo el saldo de FE-2'}),
    );
    expect(
      screen.getByLabelText('Valor a aplicar a FE-2'),
      'the balance is shown as a Colombian writes it',
    ).toHaveValue('300.000');
    expect(screen.getByRole('status')).toHaveTextContent('Cuadra');
  });

  it('says when more was applied than received', async () => {
    render(<Harness total="100" />);

    await userEvent.type(
      screen.getByLabelText('Valor a aplicar a FE-1'),
      '150',
    );

    expect(screen.getByRole('status')).toHaveTextContent(
      'Aplicaste $ 50,00 de más',
    );
  });

  it('flags a row above its balance, and one that is not an amount', async () => {
    render(<Harness total="1000000" />);

    await userEvent.type(
      screen.getByLabelText('Valor a aplicar a FE-2'),
      '300000,01',
    );
    await userEvent.type(screen.getByLabelText('Valor a aplicar a FE-1'), 'x');

    const [first, second] = screen.getAllByRole('row').slice(1);
    expect(
      within(second!).getByText('Supera el saldo de la factura.'),
    ).toBeVisible();
    expect(
      within(first!).getByText('Escribe un valor en pesos.'),
    ).toBeVisible();
    expect(screen.getByLabelText('Valor a aplicar a FE-2')).toHaveAttribute(
      'aria-invalid',
      'true',
    );
  });

  it('shows a refusal from the server on its row', () => {
    render(<Harness total="1" errors={{r2: 'Elige una factura pendiente.'}} />);

    const second = screen.getAllByRole('row')[2]!;
    expect(
      within(second).getByText('Elige una factura pendiente.'),
    ).toBeVisible();
  });
});
