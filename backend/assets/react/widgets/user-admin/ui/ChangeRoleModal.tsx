import {useState} from 'react';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {changeRole, type CompanyUser, type UserRole} from '../api/userAdminApi';

const ROLES: UserRole[] = ['owner', 'billing', 'accountant'];
const KNOWN_ERRORS = ['last_owner', 'user_not_found', 'forbidden'];

export function ChangeRoleModal({
  user,
  name,
  onClose,
  onChanged,
}: {
  user: CompanyUser;
  name: string;
  onClose: () => void;
  onChanged: (user: CompanyUser) => void;
}) {
  const {t} = useTranslation();
  const [role, setRole] = useState<UserRole>(user.role as UserRole);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (role === user.role) {
      onClose();
      return;
    }
    setBusy(true);
    setError(null);
    try {
      onChanged(await changeRole(user.id, role));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : '';
      setError(
        KNOWN_ERRORS.includes(code)
          ? t(`access.users.errors.${code}`)
          : t('common.errors.unexpected'),
      );
      setBusy(false);
    }
  };

  return (
    <FormModal
      title={t('access.users.changeRole.title', {name})}
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={error}
      submitLabel={t('access.users.changeRole.submit')}
    >
      <Field
        label={t('access.users.changeRole.role')}
        className="span-2"
        hint={t(`access.roleHints.${role}`)}
      >
        <select
          value={role}
          onChange={(e) => setRole(e.target.value as UserRole)}
        >
          {ROLES.map((value) => (
            <option key={value} value={value}>
              {t(`access.roles.${value}`)}
            </option>
          ))}
        </select>
      </Field>
      {!user.is_you && (
        <p className="muted small span-2">
          {t('access.users.changeRole.signsOut')}
        </p>
      )}
    </FormModal>
  );
}
