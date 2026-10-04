import {useEffect, useState, type FormEvent} from 'react';
import {Link, useLocation, useNavigate} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Field, FullPageLoading} from '@/shared/ui';
import {
  acceptInvitation,
  lookupInvitation,
  type Invitation,
} from '../api/invitationApi';

type State =
  | {kind: 'loading'}
  | {kind: 'invalid'}
  | {kind: 'failed'}
  | {kind: 'ready'; invitation: Invitation};

/**
 * The page an invitation e-mail opens (/invitacion#<token>): the invitee writes their name and a password and lands
 * signed in. The token is read from the address once and then taken out of it.
 */
export function AcceptInvitationPage() {
  const {t} = useTranslation();
  const location = useLocation();
  const navigate = useNavigate();
  const [token] = useState(() => location.hash.replace(/^#/, ''));
  const [state, setState] = useState<State>(
    token === '' ? {kind: 'invalid'} : {kind: 'loading'},
  );

  useEffect(() => {
    document.title = t('access.acceptInvitation.documentTitle');
  }, [t]);
  useEffect(() => {
    if (location.hash !== '') {
      navigate({pathname: location.pathname, hash: ''}, {replace: true});
    }
  }, [location.hash, location.pathname, navigate]);
  useEffect(() => {
    if (token === '') return undefined;
    let current = true;
    lookupInvitation(token).then(
      (invitation) => current && setState({kind: 'ready', invitation}),
      (error: unknown) =>
        current &&
        setState({
          kind:
            error instanceof ApiError && error.status === 404
              ? 'invalid'
              : 'failed',
        }),
    );
    return () => {
      current = false;
    };
  }, [token]);

  if (state.kind === 'loading') return <FullPageLoading />;

  return (
    <main className="auth-page">
      <div className="auth-card">
        <Brand />
        {state.kind === 'ready' ? (
          <AcceptForm
            token={token}
            invitation={state.invitation}
            onInvalid={() => setState({kind: 'invalid'})}
          />
        ) : state.kind === 'invalid' ? (
          <>
            <h1>{t('access.acceptInvitation.invalidTitle')}</h1>
            <p className="muted">{t('access.acceptInvitation.invalid')}</p>
            <p>
              <Link to="/ingresar">
                {t('access.acceptInvitation.goToSignIn')}
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

function AcceptForm({
  token,
  invitation,
  onInvalid,
}: {
  token: string;
  invitation: Invitation;
  onInvalid: () => void;
}) {
  const {t} = useTranslation();
  const {replace} = useSession();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<{name?: string; password?: string}>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found: {name?: string; password?: string} = {};
    if (name.trim() === '') found.name = t('access.validation.required');
    if (password.length < 10) {
      found.password = t('access.validation.passwordLength');
    }
    setErrors(found);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    setFailure(null);
    try {
      replace(await acceptInvitation(token, name.trim(), password));
      navigate('/', {replace: true});
    } catch (error) {
      setBusy(false);
      if (error instanceof ApiError && error.status === 404) {
        onInvalid();
        return;
      }
      setFailure(t('common.errors.unexpected'));
    }
  };

  return (
    <>
      <h1>
        {t('access.acceptInvitation.title', {
          company: invitation.company_name,
        })}
      </h1>
      <p className="muted">
        {t('access.acceptInvitation.intro', {
          role: t(`access.roles.${invitation.role}`),
          email: invitation.email,
        })}
      </p>
      <form className="form" onSubmit={submit} noValidate>
        {failure && <Alert kind="error">{failure}</Alert>}
        <Field label={t('access.acceptInvitation.name')} error={errors.name}>
          <input
            type="text"
            autoComplete="name"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </Field>
        <Field
          label={t('access.acceptInvitation.password')}
          hint={t('access.acceptInvitation.passwordHint')}
          error={errors.password}
        >
          <input
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </Field>
        {/* Lets the browser's password manager save the e-mail with the new password. */}
        <input
          type="email"
          autoComplete="username"
          value={invitation.email}
          readOnly
          hidden
        />
        <Button type="submit" busy={busy} className="btn-block">
          {t('access.acceptInvitation.submit')}
        </Button>
      </form>
    </>
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
