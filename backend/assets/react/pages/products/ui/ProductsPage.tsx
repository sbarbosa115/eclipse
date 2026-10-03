import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 6 catalog of the accounting split, which replaces this placeholder. */
export function ProductsPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.products')} />
      <ComingSoon />
    </>
  );
}
