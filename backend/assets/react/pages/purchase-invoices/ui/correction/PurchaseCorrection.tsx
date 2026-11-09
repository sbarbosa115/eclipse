import {useState} from 'react';
import {Link, useNavigate} from 'react-router-dom';
import {canVoid, type PurchaseInvoice} from '@/entities/purchase-invoice';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button} from '@/shared/ui';
import {CorrectPurchaseInvoiceModal} from './CorrectPurchaseInvoiceModal';
import './correction.css';

const BASE = '/facturas-compra';

/**
 * Corregir on the invoice's page (observations §4.6): on an emitted invoice nothing is paid on, the banner and the
 * button that void it and open an editable copy; on a paid one, what to do first; and, either side of a correction,
 * the link to the other invoice.
 */
export function PurchaseCorrection({
  invoice,
  canWrite,
}: {
  invoice: PurchaseInvoice;
  canWrite: boolean;
}) {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const paid = invoice.status === 'partially_paid' || invoice.status === 'paid';

  return (
    <>
      {canWrite && canVoid(invoice) && (
        <Alert kind="info">
          <span className="purchase-correction">
            <span>{t('purchaseInvoice.correct.banner')}</span>
            <Button variant="secondary" size="sm" onClick={() => setOpen(true)}>
              {t('purchaseInvoice.correct.action')}
            </Button>
          </span>
        </Alert>
      )}
      {canWrite && paid && (
        <Alert kind="info">{t('purchaseInvoice.correct.paidFirst')}</Alert>
      )}
      {invoice.corrected_by_id && (
        <Alert kind="info">
          {t('purchaseInvoice.correct.correctedBy')}{' '}
          <Link to={`${BASE}/${invoice.corrected_by_id}`}>
            {t('purchaseInvoice.correct.openReplacement')}
          </Link>
        </Alert>
      )}
      {invoice.corrected_from_id && (
        <Alert kind="info">
          {t('purchaseInvoice.correct.corrects')}{' '}
          <Link to={`${BASE}/${invoice.corrected_from_id}`}>
            {t('purchaseInvoice.correct.openOriginal')}
          </Link>
        </Alert>
      )}
      {open && (
        <CorrectPurchaseInvoiceModal
          invoice={invoice}
          onClose={() => setOpen(false)}
          onCorrected={(draft) =>
            navigate(`${BASE}/${draft.id}`, {
              state: {
                notice: t('purchaseInvoice.correct.done', {
                  number: invoice.number ?? '',
                }),
              },
            })
          }
        />
      )}
    </>
  );
}
