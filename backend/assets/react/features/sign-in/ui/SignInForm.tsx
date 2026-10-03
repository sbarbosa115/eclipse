import {useState, type FormEvent} from 'react';
import {signIn, useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Field} from '@/shared/ui';

/** E-mail and password. A wrong pair says so without telling which part was wrong. */
export function SignInForm() {
  const {t} = useTranslation();
  const {replace} = useSession();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (email.trim() === '' || password === '') {
      setError(t('auth.validation.required'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      replace(await signIn(email.trim(), password));
    } catch (e) {
      setError(
        e instanceof ApiError && e.status === 401
          ? t('auth.signIn.invalid')
          : e instanceof ApiError && e.status === 429
            ? t('common.errors.tooManyRequests')
            : t('common.errors.unexpected'),
      );
      setBusy(false);
    }
  };

  return (
    <form className="form" onSubmit={submit} noValidate>
      {error && <Alert kind="error">{error}</Alert>}
      <Field label={t('auth.signIn.email')}>
        <input
          type="email"
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
      </Field>
      <Field label={t('auth.signIn.password')}>
        <input
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </Field>
      <Button type="submit" busy={busy} className="btn-block">
        {t('auth.signIn.submit')}
      </Button>
    </form>
  );
}
