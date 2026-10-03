import {useState, type FormEvent} from 'react';
import {signUp, useSession, type SignUpData} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, Field} from '@/shared/ui';

type Errors = Partial<Record<keyof SignUpData, string>>;

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const NIT = /^[\d.\- ]{5,20}$/;

const EMPTY: SignUpData = {
  company_name: '',
  nit: '',
  owner_name: '',
  email: '',
  password: '',
};

/** Creates the company and its owner; the server then signs the owner in. */
export function SignUpForm() {
  const {t} = useTranslation();
  const {replace} = useSession();
  const [data, setData] = useState<SignUpData>(EMPTY);
  const [errors, setErrors] = useState<Errors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (field: keyof SignUpData) => (value: string) =>
    setData((d) => ({...d, [field]: value}));

  const validate = (): Errors => {
    const found: Errors = {};
    for (const field of Object.keys(EMPTY) as (keyof SignUpData)[]) {
      if (data[field].trim() === '')
        found[field] = t('auth.validation.required');
    }
    if (!found.email && !EMAIL.test(data.email.trim())) {
      found.email = t('auth.validation.email');
    }
    if (!found.nit && !NIT.test(data.nit.trim())) {
      found.nit = t('auth.validation.nit');
    }
    if (!found.password && data.password.length < 10) {
      found.password = t('auth.validation.passwordLength');
    }
    return found;
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length > 0) return;
    setBusy(true);
    setFailure(null);
    try {
      replace(
        await signUp({
          ...data,
          company_name: data.company_name.trim(),
          nit: data.nit.trim(),
          owner_name: data.owner_name.trim(),
          email: data.email.trim(),
        }),
      );
    } catch (e) {
      setBusy(false);
      if (e instanceof ApiError && e.status === 422) {
        const body = e.body as {
          violations?: {field: string; message: string}[];
        };
        const server: Errors = {};
        for (const v of body.violations ?? []) {
          const field = v.field === 'identification_number' ? 'nit' : v.field;
          server[field as keyof SignUpData] = v.message;
        }
        setErrors(server);
        return;
      }
      setFailure(
        e instanceof ApiError && e.status === 429
          ? t('common.errors.tooManyRequests')
          : t('common.errors.unexpected'),
      );
    }
  };

  return (
    <form className="form" onSubmit={submit} noValidate>
      {failure && <Alert kind="error">{failure}</Alert>}
      <Field label={t('auth.signUp.companyName')} error={errors.company_name}>
        <input
          value={data.company_name}
          autoComplete="organization"
          onChange={(e) => set('company_name')(e.target.value)}
        />
      </Field>
      <Field
        label={t('auth.signUp.nit')}
        hint={t('auth.signUp.nitHint')}
        error={errors.nit}
      >
        <input
          inputMode="numeric"
          value={data.nit}
          onChange={(e) => set('nit')(e.target.value)}
        />
      </Field>
      <Field label={t('auth.signUp.ownerName')} error={errors.owner_name}>
        <input
          value={data.owner_name}
          autoComplete="name"
          onChange={(e) => set('owner_name')(e.target.value)}
        />
      </Field>
      <Field label={t('auth.signUp.email')} error={errors.email}>
        <input
          type="email"
          autoComplete="email"
          value={data.email}
          onChange={(e) => set('email')(e.target.value)}
        />
      </Field>
      <Field
        label={t('auth.signUp.password')}
        hint={t('auth.signUp.passwordHint')}
        error={errors.password}
      >
        <input
          type="password"
          autoComplete="new-password"
          value={data.password}
          onChange={(e) => set('password')(e.target.value)}
        />
      </Field>
      <Button type="submit" busy={busy} className="btn-block">
        {t('auth.signUp.submit')}
      </Button>
    </form>
  );
}
