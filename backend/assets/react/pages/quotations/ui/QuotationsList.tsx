import {Link, useLocation} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {PageHeader} from '@/shared/ui';
import {QuotationList} from '@/widgets/quotation-list';
import {canWriteQuotations} from '../model/access';

/** /cotizaciones: the list, with "Nueva cotización" in the header. */
export function QuotationsList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWriteQuotations(session);
  return (
    <>
      <PageHeader
        title={t('quotation.title')}
        subtitle={t('quotation.subtitle')}
        actions={
          writer && (
            <Link to="nueva" className="btn btn-primary">
              {t('quotation.new')}
            </Link>
          )
        }
      />
      <QuotationList
        canWrite={writer}
        notice={(location.state as {notice?: string} | null)?.notice ?? null}
      />
    </>
  );
}
