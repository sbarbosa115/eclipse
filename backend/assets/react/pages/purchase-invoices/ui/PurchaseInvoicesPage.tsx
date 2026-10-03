import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 9 purchase-invoice of the accounting split, which replaces this placeholder. */
export function PurchaseInvoicesPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.purchaseInvoices')} />
      <ComingSoon />
    </>
  );
}
