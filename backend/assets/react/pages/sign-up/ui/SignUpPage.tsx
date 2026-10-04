import {useEffect} from 'react';
import {Link, Navigate} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {SignUpForm} from '@/features/sign-up';
import {useTranslation} from '@/shared/i18n';
import {FullPageLoading} from '@/shared/ui';

export function SignUpPage() {
  const {t} = useTranslation();
  const {status} = useSession();
  useEffect(() => {
    document.title = t('auth.signUp.documentTitle');
  }, [t]);

  if (status === 'loading') return <FullPageLoading />;
  if (status === 'signed-in') return <Navigate to="/" replace />;

  return (
    <main className="auth-page">
      <div className="auth-card">
        <div className="brand">
          <span className="brand-name">{t('auth.brand.name')}</span>
          <span className="brand-sub">{t('auth.brand.sub')}</span>
        </div>
        <h1>{t('auth.signUp.title')}</h1>
        <p className="muted">{t('auth.signUp.intro')}</p>
        <SignUpForm />
        <p className="muted">
          {t('auth.signUp.haveAccount')}{' '}
          <Link to="/ingresar">{t('auth.signUp.signInLink')}</Link>
        </p>
      </div>
    </main>
  );
}
