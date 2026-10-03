import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 5 terceros of the accounting split, which replaces this placeholder. */
export function TercerosPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.terceros')} />
      <ComingSoon />
    </>
  );
}
