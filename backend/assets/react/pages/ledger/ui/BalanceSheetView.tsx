import {useSearchParams} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {Alert, ErrorState, FilterBar, Loading, TabIntro} from '@/shared/ui';
import {fetchBalanceSheet} from '../api/ledgerApi';
import {today} from '../lib/period';
import {useLoaded} from '../lib/useLoaded';
import {DateFilter} from './DateFilter';
import {LedgerExports} from './LedgerExports';
import {StatementTable} from './StatementTable';

/** §9 Q25: the basic balance general at a date, from the balance de prueba. */
export function BalanceSheetView() {
  const {t} = useTranslation();
  const [params, setParams] = useSearchParams();
  const date = params.get('date') ?? today();
  const {data, failed, retry} = useLoaded(date, () => fetchBalanceSheet(date));

  return (
    <>
      <TabIntro>{t('ledger.balanceSheet.intro')}</TabIntro>
      <FilterBar>
        <DateFilter
          label={t('ledger.period.date')}
          value={date}
          onChange={(value) => setParams({date: value})}
        />
      </FilterBar>
      <LedgerExports book="balanceSheet" params={{date}} />
      {failed ? (
        <ErrorState message={t('common.loadFailed')} onRetry={retry} />
      ) : !data ? (
        <Loading />
      ) : (
        <>
          <Alert kind={data.balanced ? 'success' : 'error'}>
            {data.balanced
              ? t('ledger.balanceSheet.balanced')
              : t('ledger.balanceSheet.unbalanced')}
          </Alert>
          <StatementTable
            sections={data.sections}
            summary={[
              {
                label: t('ledger.balanceSheet.assets'),
                amount: data.total_assets,
              },
              {
                label: t('ledger.balanceSheet.liabilities'),
                amount: data.total_liabilities,
              },
              {
                label: t('ledger.balanceSheet.currentEarnings'),
                amount: data.current_earnings,
              },
              {
                label: t('ledger.balanceSheet.equity'),
                amount: data.total_equity,
              },
            ]}
          />
        </>
      )}
    </>
  );
}
