import {useTranslation} from '@/shared/i18n';
import {ExportLinks, exportUrl} from '@/widgets/report-table';

type Book = 'journal' | 'trialBalance' | 'incomeStatement' | 'balanceSheet';

const PATHS: Record<Book, string> = {
  journal: '/reports/ledger/journal/export',
  trialBalance: '/reports/ledger/trial-balance/export',
  incomeStatement: '/reports/ledger/income-statement/export',
  balanceSheet: '/reports/ledger/balance-sheet/export',
};

/** A book's CSV and PDF, with the filters on screen (the same export Reportes › Exportar offers). */
export function LedgerExports({
  book,
  params,
}: {
  book: Book;
  params: Record<string, string | undefined>;
}) {
  const {t} = useTranslation();
  return (
    <div className="ledger-exports">
      <ExportLinks
        target={{
          name: t(`ledger.tabs.${book}`),
          csv: exportUrl(PATHS[book], 'csv', params),
          pdf: exportUrl(PATHS[book], 'pdf', params),
        }}
      />
    </div>
  );
}
