import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 8 sales-invoice of the accounting split, which replaces this placeholder. */
export function SalesInvoicesPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.salesInvoices')} />
      <ComingSoon />
    </>
  );
}
