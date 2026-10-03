import './tercero.css';
import {useTranslation} from '@/shared/i18n';

/** A tercero's roles as small labels: a tercero may be client, supplier, employee and other at once. */
export function RoleBadges({roles}: {roles: readonly string[]}) {
  const {t} = useTranslation();
  return (
    <span className="role-badges">
      {roles.map((role) => (
        <span key={role} className="badge badge-neutral">
          {t(`terceros.roles.${role}`)}
        </span>
      ))}
    </span>
  );
}
