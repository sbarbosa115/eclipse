import {Link, useLocation} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {PageHeader} from '@/shared/ui';
import {SalesInvoiceList} from '@/widgets/sales-invoice-list';
import {canWriteSalesInvoices} from '../model/access';

/** /facturas-venta: the list, with "Nueva factura" in the header. */
export function SalesInvoicesList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWriteSalesInvoices(session);

  return (
    <>
      <PageHeader
        title={t('salesInvoice.title')}
        subtitle={t('salesInvoice.subtitle')}
        actions={
          writer && (
            <Link to="nueva" className="btn btn-primary">
              {t('salesInvoice.new')}
            </Link>
          )
        }
      />
      <SalesInvoiceList
        canWrite={writer}
        notice={(location.state as {notice?: string} | null)?.notice ?? null}
      />
    </>
  );
}
