import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 10 quotation of the accounting split, which replaces this placeholder. */
export function QuotationsPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.quotations')} />
      <ComingSoon />
    </>
  );
}
