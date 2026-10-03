import {useTranslation} from '@/shared/i18n';
import {ComingSoon, PageHeader} from '@/shared/ui';

/** Owned by item 13 reports of the accounting split, which replaces this placeholder. */
export function DashboardPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.nav.dashboard')} />
      <ComingSoon />
    </>
  );
}
