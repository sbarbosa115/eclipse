import {formatMoney} from '@/shared/lib';

/** A money cell: right-aligned, and empty for a zero débito or crédito when `blankZero`. */
export function MoneyCell({
  amount,
  blankZero = false,
}: {
  amount: string;
  blankZero?: boolean;
}) {
  return (
    <td className="ledger-num">
      {blankZero && Number(amount) === 0 ? '' : formatMoney(amount)}
    </td>
  );
}
