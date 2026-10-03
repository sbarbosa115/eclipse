import {useEffect, useState, type FormEvent} from 'react';
import {Link, useLocation, useNavigate} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Field, FullPageLoading} from '@/shared/ui';
import {checkResetLink, resetPassword} from '../api/resetPasswordApi';

type State = 'loading' | 'ready' | 'invalid' | 'failed';

/**
 * The page a password-reset e-mail opens (/restablecer-contrasena#<token>): a new password, then signed in. Every
 * other session of the person ends. The token is read from the address once and then taken out of it.
 */
export function ResetPasswordPage() {
  const {t} = useTranslation();
  const {replace} = useSession();
  const location = useLocation();
  const navigate = useNavigate();
  const [token] = useState(() => location.hash.replace(/^#/, ''));
  const [state, setState] = useState<State>(
    token === '' ? 'invalid' : 'loading',
  );
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    document.title = t('access.resetPassword.documentTitle');
  }, [t]);
  useEffect(() => {
    if (location.hash !== '') {
      navigate({pathname: location.pathname, hash: ''}, {replace: true});
    }
  }, [location.hash, location.pathname, navigate]);
  useEffect(() => {
    if (token === '') return undefined;
    let current = true;
    checkResetLink(token).then(
      () => current && setState('ready'),
      (e: unknown) =>
        current &&
        setState(
          e instanceof ApiError && e.status === 404 ? 'invalid' : 'failed',
        ),
    );
    return () => {
      current = false;
    };
  }, [token]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (password.length < 10) {
      setError(t('access.validation.passwordLength'));
      return;
    }
    setError(null);
    setFailure(null);
    setBusy(true);
    try {
      replace(await resetPassword(token, password));
      navigate('/', {replace: true});
    } catch (e) {
      setBusy(false);
      if (e instanceof ApiError && e.status === 404) {
        setState('invalid');
        return;
      }
      setFailure(t('common.errors.unexpected'));
    }
  };

  if (state === 'loading') return <FullPageLoading />;

  return (
    <main className="auth-page">
      <div className="auth-card">
        <div className="brand">
          <span className="brand-name">{t('auth.brand.name')}</span>
          <span className="brand-sub">{t('auth.brand.sub')}</span>
        </div>
        {state === 'ready' ? (
          <>
            <h1>{t('access.resetPassword.title')}</h1>
            <p className="muted">{t('access.resetPassword.intro')}</p>
            <form className="form" onSubmit={submit} noValidate>
              {failure && <Alert kind="error">{failure}</Alert>}
              <Field
                label={t('access.resetPassword.password')}
                hint={t('access.resetPassword.passwordHint')}
                error={error}
              >
                <input
                  type="password"
                  autoComplete="new-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </Field>
              <Button type="submit" busy={busy} className="btn-block">
                {t('access.resetPassword.submit')}
              </Button>
            </form>
          </>
        ) : state === 'invalid' ? (
          <>
            <h1>{t('access.resetPassword.invalidTitle')}</h1>
            <p className="muted">{t('access.resetPassword.invalid')}</p>
            <p>
              <Link to="/recuperar-contrasena">
                {t('access.resetPassword.requestAgain')}
              </Link>
            </p>
          </>
        ) : (
          <Alert kind="error">{t('common.loadFailed')}</Alert>
        )}
      </div>
    </main>
  );
}
