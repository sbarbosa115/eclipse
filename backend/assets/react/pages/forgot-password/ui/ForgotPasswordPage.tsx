import {useEffect, useState, type FormEvent} from 'react';
import {Link} from 'react-router-dom';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Field} from '@/shared/ui';
import {requestPasswordReset} from '../api/passwordResetApi';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * "¿Olvidaste tu contraseña?" (/recuperar-contrasena): asks for a reset link. The answer is the same whether or not
 * the e-mail has an account, so the page never tells who uses Mustang.
 */
export function ForgotPasswordPage() {
  const {t} = useTranslation();
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    document.title = t('access.forgotPassword.documentTitle');
  }, [t]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const address = email.trim();
    if (!EMAIL.test(address)) {
      setError(
        address === ''
          ? t('access.validation.required')
          : t('access.validation.email'),
      );
      return;
    }
    setError(null);
    setFailure(null);
    setBusy(true);
    try {
      await requestPasswordReset(address);
      setSentTo(address);
    } catch (e) {
      setFailure(
        e instanceof ApiError && e.status === 429
          ? t('common.errors.tooManyRequests')
          : t('common.errors.unexpected'),
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="auth-page">
      <div className="auth-card">
        <div className="brand">
          <span className="brand-name">{t('auth.brand.name')}</span>
          <span className="brand-sub">{t('auth.brand.sub')}</span>
        </div>
        <h1>{t('access.forgotPassword.title')}</h1>
        {sentTo ? (
          <Alert kind="success">
            {t('access.forgotPassword.sent', {email: sentTo})}
          </Alert>
        ) : (
          <>
            <p className="muted">{t('access.forgotPassword.intro')}</p>
            <form className="form" onSubmit={submit} noValidate>
              {failure && <Alert kind="error">{failure}</Alert>}
              <Field label={t('access.forgotPassword.email')} error={error}>
                <input
                  type="email"
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </Field>
              <Button type="submit" busy={busy} className="btn-block">
                {t('access.forgotPassword.submit')}
              </Button>
            </form>
          </>
        )}
        <p className="muted">
          <Link to="/ingresar">{t('access.forgotPassword.backToSignIn')}</Link>
        </p>
      </div>
    </main>
  );
}
