import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 11 cash-receipt of the accounting split, which replaces this placeholder. */
export function CashReceiptsPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.cashReceipts')} />
      <ComingSoon />
    </>
  );
}
