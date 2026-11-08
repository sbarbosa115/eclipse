import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';

/**
 * *Según libros* under a total: what the accounts moved in the period, and whether the documents agree. The words say
 * it (cuadra / diferencia), the colour only underlines it.
 */
export function BooksCheck({
  accounts,
  ledger,
  difference,
}: {
  accounts: string;
  ledger: string;
  difference: string;
}) {
  const {t} = useTranslation();
  const matches = Number(difference) === 0;
  return (
    <p className={`tax-books ${matches ? '' : 'tax-books-off'}`}>
      <span>
        {t('taxReports.books.ledger', {accounts, amount: formatMoney(ledger)})}
      </span>
      <span className="tax-books-verdict">
        {matches
          ? t('taxReports.books.matches')
          : t('taxReports.books.differs', {amount: formatMoney(difference)})}
      </span>
    </p>
  );
}
