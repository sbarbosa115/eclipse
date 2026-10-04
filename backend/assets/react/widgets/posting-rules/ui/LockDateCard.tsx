import {useEffect, useState} from 'react';
import {formatDate} from '@/shared/lib';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Card, DateInput, Field} from '@/shared/ui';
import {fetchLockDate, moveLockDate} from '../api/rulesApi';
import {errorMessage} from '../lib/errorMessage';

/** The fecha de bloqueo contable (§4.1): shown to everyone, moved by the owner and the accountant. */
export function LockDateCard({keeper}: {keeper: boolean}) {
  const {t} = useTranslation();
  const [lockedUntil, setLockedUntil] = useState<string | null | undefined>(
    undefined,
  );
  const [date, setDate] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    fetchLockDate()
      .then((answer) => setLockedUntil(answer.locked_until ?? null))
      .catch(() => setLockedUntil(null));
  }, []);

  const save = () => {
    setBusy(true);
    setError(null);
    setNotice(null);
    moveLockDate(date)
      .then((answer) => {
        setLockedUntil(answer.locked_until ?? null);
        setNotice(
          t('ledger.lockDate.saved', {
            date: formatDate(answer.locked_until ?? date),
          }),
        );
      })
      .catch((e: unknown) => setError(errorMessage(t, e)))
      .finally(() => setBusy(false));
  };

  return (
    <Card title={t('ledger.lockDate.title')}>
      <p className="small muted">{t('ledger.lockDate.intro')}</p>
      {lockedUntil !== undefined && (
        <p>
          {lockedUntil
            ? t('ledger.lockDate.current', {date: formatDate(lockedUntil)})
            : t('ledger.lockDate.open')}
        </p>
      )}
      <Alert kind="error">{error}</Alert>
      <Alert kind="success">{notice}</Alert>
      {keeper && (
        <form
          className="lock-date-form"
          noValidate
          onSubmit={(event) => {
            event.preventDefault();
            save();
          }}
        >
          <Field label={t('ledger.lockDate.label')}>
            <DateInput value={date} onChange={setDate} />
          </Field>
          <Button type="submit" busy={busy} disabled={date === ''}>
            {t('ledger.lockDate.save')}
          </Button>
        </form>
      )}
    </Card>
  );
}
