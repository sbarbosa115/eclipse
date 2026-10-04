import {useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {ActionButton, Alert, Button, Field, Modal} from '@/shared/ui';

export interface VoidDocumentModalProps {
  title: string;
  /** What voiding does (a reversing entry, the number is kept…). */
  body: string;
  reasonLabel: string;
  /** Shown when the reason is left empty. */
  reasonRequired: string;
  confirmLabel: string;
  /** Voids; rejects with the message to show, and the dialog stays open. */
  onVoid: (reason: string) => Promise<void>;
  onClose: () => void;
}

/** Anular (§4.12) for any emitted document: asks for the reason, which is recorded with who voided it and when. */
export function VoidDocumentModal({
  title,
  body,
  reasonLabel,
  reasonRequired,
  confirmLabel,
  onVoid,
  onClose,
}: VoidDocumentModalProps) {
  const {t} = useTranslation();
  const [reason, setReason] = useState('');
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);

  const submit = async () => {
    if (reason.trim() === '') {
      setMissing(true);
      return;
    }
    setBusy(true);
    setFailure(null);
    try {
      await onVoid(reason.trim());
    } catch (error) {
      setFailure(error instanceof Error ? error.message : String(error));
      setBusy(false);
    }
  };

  return (
    <Modal title={title} onClose={() => !busy && onClose()}>
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <Alert kind="error">{failure}</Alert>
        <p>{body}</p>
        <Field label={reasonLabel} error={missing ? reasonRequired : null}>
          <textarea
            rows={3}
            maxLength={500}
            value={reason}
            onChange={(event) => {
              setReason(event.target.value);
              if (event.target.value.trim() !== '') setMissing(false);
            }}
          />
        </Field>
        <div className="form-actions">
          <Button variant="ghost" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <ActionButton action="danger" size="md" type="submit" busy={busy}>
            {confirmLabel}
          </ActionButton>
        </div>
      </form>
    </Modal>
  );
}
