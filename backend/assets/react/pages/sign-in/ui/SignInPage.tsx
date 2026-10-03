import {useEffect} from 'react';
import {Link, Navigate, useLocation} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {SignInForm} from '@/features/sign-in';
import {useTranslation} from '@/shared/i18n';
import {FullPageLoading} from '@/shared/ui';

export function SignInPage() {
  const {t} = useTranslation();
  const {status} = useSession();
  const location = useLocation();
  useEffect(() => {
    document.title = t('auth.signIn.documentTitle');
  }, [t]);

  if (status === 'loading') return <FullPageLoading />;
  if (status === 'signed-in') {
    const from = (location.state as {from?: string} | null)?.from ?? '/';
    return <Navigate to={from} replace />;
  }

  return (
    <main className="auth-page">
      <div className="auth-card">
        <Brand />
        <h1>{t('auth.signIn.title')}</h1>
        <SignInForm />
        <p className="muted">
          {t('auth.signIn.noAccount')}{' '}
          <Link to="/registro">{t('auth.signIn.signUpLink')}</Link>
        </p>
      </div>
    </main>
  );
}

function Brand() {
  const {t} = useTranslation();
  return (
    <div className="brand">
      <span className="brand-name">{t('auth.brand.name')}</span>
      <span className="brand-sub">{t('auth.brand.sub')}</span>
    </div>
  );
}
