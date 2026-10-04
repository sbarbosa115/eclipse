import {useState} from 'react';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {Field, FormModal} from '@/shared/ui';
import {
  inviteUser,
  type InvitableRole,
  type InvitedUser,
} from '../api/inviteApi';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const ROLES: InvitableRole[] = ['billing', 'accountant'];

/**
 * The owner invites someone by e-mail with a role (§4.14). "Invitar a tu contador" opens it with the accountant role.
 * The invitee gets a link that works once, for seven days.
 */
export function InviteUserModal({
  defaultRole = 'billing',
  onClose,
  onInvited,
}: {
  defaultRole?: InvitableRole;
  onClose: () => void;
  onInvited: (user: InvitedUser) => void;
}) {
  const {t} = useTranslation();
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<InvitableRole>(defaultRole);
  const [errors, setErrors] = useState<{email?: string; role?: string}>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    const address = email.trim();
    if (address === '') {
      setErrors({email: t('access.validation.required')});
      return;
    }
    if (!EMAIL.test(address)) {
      setErrors({email: t('access.validation.email')});
      return;
    }
    setErrors({});
    setFailure(null);
    setBusy(true);
    try {
      onInvited(await inviteUser(address, role));
    } catch (error) {
      setBusy(false);
      const violations =
        error instanceof ApiError && error.status === 422
          ? ((error.body as {violations?: {field: string; message: string}[]})
              .violations ?? [])
          : [];
      if (violations.length > 0) {
        const found: {email?: string; role?: string} = {};
        for (const v of violations) {
          if (v.field === 'email' || v.field === 'role') {
            found[v.field] ??= v.message;
          }
        }
        setErrors(found);
        return;
      }
      setFailure(
        error instanceof ApiError && error.status === 403
          ? t('access.users.errors.forbidden')
          : error instanceof ApiError && error.code === 'too_many_emails'
            ? t('access.users.errors.too_many_emails')
            : t('common.errors.unexpected'),
      );
    }
  };

  return (
    <FormModal
      title={t(
        defaultRole === 'accountant'
          ? 'access.invite.titleAccountant'
          : 'access.invite.title',
      )}
      onClose={onClose}
      onSubmit={() => void submit()}
      busy={busy}
      error={failure}
      submitLabel={t('access.invite.submit')}
    >
      <p className="muted span-2">{t('access.invite.intro')}</p>
      <Field label={t('access.invite.email')} error={errors.email}>
        <input
          type="email"
          autoComplete="off"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
      </Field>
      <Field
        label={t('access.invite.role')}
        error={errors.role}
        hint={t(`access.roleHints.${role}`)}
      >
        <select
          value={role}
          onChange={(e) => setRole(e.target.value as InvitableRole)}
        >
          {ROLES.map((value) => (
            <option key={value} value={value}>
              {t(`access.roles.${value}`)}
            </option>
          ))}
        </select>
      </Field>
    </FormModal>
  );
}
