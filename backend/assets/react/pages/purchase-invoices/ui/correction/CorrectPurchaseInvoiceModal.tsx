import {useState} from 'react';
import {
  purchaseErrorMessage,
  type PurchaseInvoice,
} from '@/entities/purchase-invoice';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {correctPurchaseInvoice} from '../../api/correctPurchaseInvoice';

/** The void reason holds 500 characters, "Corregida: " included (CorrectPurchaseInvoiceInput). */
const REASON_MAX = 489;

/** Corregir: asks why, then voids the invoice and hands back the editable copy. */
export function CorrectPurchaseInvoiceModal({
  invoice,
  onClose,
  onCorrected,
}: {
  invoice: {id: string; number?: string | null};
  onClose: () => void;
  onCorrected: (draft: PurchaseInvoice) => void;
}) {
  const {t} = useTranslation();
  const [reason, setReason] = useState('');
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (reason.trim() === '') {
      setFieldError(t('purchaseInvoice.correct.reasonRequired'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      onCorrected(await correctPurchaseInvoice(invoice.id, reason.trim()));
    } catch (failure) {
      setError(purchaseErrorMessage(failure, t));
      setBusy(false);
    }
  };

  return (
    <FormModal
      title={t('purchaseInvoice.correct.title', {number: invoice.number ?? ''})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={error}
      submitLabel={t('purchaseInvoice.correct.confirm')}
    >
      <p className="small muted">{t('purchaseInvoice.correct.body')}</p>
      <Field label={t('purchaseInvoice.correct.reason')} error={fieldError}>
        <textarea
          rows={3}
          maxLength={REASON_MAX}
          value={reason}
          onChange={(e) => {
            setReason(e.target.value);
            setFieldError(null);
          }}
        />
      </Field>
    </FormModal>
  );
}
