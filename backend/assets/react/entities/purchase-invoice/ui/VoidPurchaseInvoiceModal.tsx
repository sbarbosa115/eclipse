import {useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {
  voidPurchaseInvoice,
  type PurchaseInvoice,
} from '../api/purchaseInvoiceApi';
import {purchaseErrorMessage} from '../model/status';

/** Anular (§4.12): asks why, then voids; the reason is kept with the invoice. */
export function VoidPurchaseInvoiceModal({
  invoice,
  onClose,
  onVoided,
}: {
  invoice: {id: string; number?: string | null};
  onClose: () => void;
  onVoided: (invoice: PurchaseInvoice) => void;
}) {
  const {t} = useTranslation();
  const [reason, setReason] = useState('');
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (reason.trim() === '') {
      setFieldError(t('purchaseInvoice.void.reasonRequired'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      onVoided(await voidPurchaseInvoice(invoice.id, reason.trim()));
    } catch (failure) {
      setError(purchaseErrorMessage(failure, t));
      setBusy(false);
    }
  };

  return (
    <FormModal
      title={t('purchaseInvoice.void.title', {number: invoice.number ?? ''})}
      onClose={onClose}
      onSubmit={submit}
      busy={busy}
      error={error}
      submitLabel={t('purchaseInvoice.void.confirm')}
    >
      <p className="small muted">{t('purchaseInvoice.void.body')}</p>
      <Field label={t('purchaseInvoice.void.reason')} error={fieldError}>
        <textarea
          rows={3}
          maxLength={500}
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
