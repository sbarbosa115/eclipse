import {Link} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {EmptyState, PageHeader} from '@/shared/ui';

export function NotFoundPage() {
  const {t} = useTranslation();
  return (
    <>
      <PageHeader title={t('shell.notFound.title')} />
      <EmptyState action={<Link to="/">{t('shell.notFound.back')}</Link>}>
        {t('shell.notFound.body')}
      </EmptyState>
    </>
  );
}
