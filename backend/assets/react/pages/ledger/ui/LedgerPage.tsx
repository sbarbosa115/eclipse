import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 3 ledger of the accounting split, which replaces this placeholder. */
export function LedgerPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.ledger')} />
      <ComingSoon />
    </>
  );
}
