import {useCallback, useEffect, useState} from 'react';
import {can, useSession} from '@/entities/session';
import {InviteUserModal, type InvitableRole} from '@/features/invite-user';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {
  ActionButton,
  Actions,
  Alert,
  Badge,
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  Modal,
  Row,
  RowLegend,
  TabIntro,
} from '@/shared/ui';
import {
  listUsers,
  resendInvitation,
  setUserActive,
  type CompanyUser,
} from '../api/userAdminApi';
import {formatDateTime, formatDay} from '../lib/dateTime';
import {ChangeRoleModal} from './ChangeRoleModal';

const STATUSES = ['active', 'invited', 'deactivated'] as const;
// The row colour of each status (the kit's tones): an invitee waits, a deactivated user is out.
const TONE: Record<string, string | null> = {
  active: null,
  invited: 'prospect',
  deactivated: 'cancelled',
};
const KNOWN_ERRORS = [
  'last_owner',
  'cannot_deactivate_yourself',
  'not_an_invitation',
  'user_not_found',
  'forbidden',
];

type Notice = {kind: 'success' | 'error'; text: string} | null;

/**
 * Configuración › Usuarios (§4.14): the owner sees who enters the company, invites people (and their accountant),
 * changes roles, resends invitations and deactivates. Everyone else is told only the owner does this.
 */
export function UserAdmin() {
  const {t} = useTranslation();
  const {session} = useSession();
  if (!can(session, 'MANAGE_USERS')) {
    return <EmptyState>{t('access.users.ownerOnly')}</EmptyState>;
  }
  return <UserList />;
}

function UserList() {
  const {t} = useTranslation();
  const [users, setUsers] = useState<CompanyUser[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [status, setStatus] = useState('all');
  const [inviting, setInviting] = useState<InvitableRole | null>(null);
  const [changing, setChanging] = useState<CompanyUser | null>(null);
  const [deactivating, setDeactivating] = useState<CompanyUser | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice>(null);

  const [reads, setReads] = useState(0);
  const load = useCallback(() => setReads((n) => n + 1), []);
  useEffect(() => {
    let current = true;
    listUsers().then(
      (items) => {
        if (!current) return;
        setUsers(items);
        setFailed(false);
      },
      () => current && setFailed(true),
    );
    return () => {
      current = false;
    };
  }, [reads]);

  const nameOf = (user: CompanyUser) => user.name || user.email;
  const explain = (error: unknown): string => {
    const code = error instanceof ApiError ? error.code : '';
    return KNOWN_ERRORS.includes(code)
      ? t(`access.users.errors.${code}`)
      : t('common.errors.unexpected');
  };

  const act = async (
    user: CompanyUser,
    action: () => Promise<CompanyUser>,
    success: (changed: CompanyUser) => string,
  ) => {
    setBusyId(user.id);
    try {
      const changed = await action();
      setNotice({kind: 'success', text: success(changed)});
      load();
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setBusyId(null);
      setDeactivating(null);
    }
  };

  if (failed) {
    return <ErrorState message={t('common.loadFailed')} onRetry={load} />;
  }
  if (users === null) return <Loading />;

  const shown = users.filter((u) => status === 'all' || u.status === status);

  return (
    <>
      <TabIntro
        action={
          <div className="row-actions">
            <Button
              variant="secondary"
              onClick={() => setInviting('accountant')}
            >
              {t('access.users.inviteAccountant')}
            </Button>
            <Button onClick={() => setInviting('billing')}>
              {t('access.users.invite')}
            </Button>
          </div>
        }
      >
        {t('access.users.intro')}
      </TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <FilterBar
        filters={[
          {
            name: 'status',
            label: t('access.users.filters.status'),
            value: status,
            onChange: setStatus,
            options: [
              {value: 'all', label: t('access.users.filters.all')},
              ...STATUSES.map((value) => ({
                value,
                label: t(`access.status.${value}`),
              })),
            ],
          },
        ]}
      />
      {users.length === 0 ? (
        <EmptyState>{t('access.users.empty')}</EmptyState>
      ) : shown.length === 0 ? (
        <EmptyState
          action={
            <Button variant="ghost" onClick={() => setStatus('all')}>
              {t('common.showAll')}
            </Button>
          }
        >
          {t('access.users.filteredEmpty')}
        </EmptyState>
      ) : (
        <>
          <RowLegend
            statuses={[
              {value: 'prospect', label: t('access.status.invited')},
              {value: 'cancelled', label: t('access.status.deactivated')},
            ]}
          />
          <DataTable
            columns={[
              t('access.users.columns.name'),
              t('access.users.columns.email'),
              t('access.users.columns.role'),
              t('access.users.columns.lastSignIn'),
            ]}
            rows={shown}
            renderRow={(user) => (
              <Row
                key={user.id}
                status={TONE[user.status] ?? null}
                label={
                  user.status === 'active'
                    ? null
                    : t(`access.status.${user.status}`)
                }
              >
                <td>
                  {user.name || (
                    <span className="muted">{t('access.users.noName')}</span>
                  )}{' '}
                  {user.is_you && (
                    <Badge value="owner">{t('access.users.you')}</Badge>
                  )}
                </td>
                <td>{user.email}</td>
                <td>{t(`access.roles.${user.role}`)}</td>
                <td>
                  <LastSeen user={user} />
                </td>
                <Actions>
                  {user.status !== 'deactivated' && (
                    <ActionButton
                      action="edit"
                      onClick={() => setChanging(user)}
                    >
                      {t('access.users.actions.changeRole')}
                    </ActionButton>
                  )}
                  {user.status === 'invited' && (
                    <ActionButton
                      action="setup"
                      busy={busyId === user.id}
                      onClick={() =>
                        void act(
                          user,
                          () => resendInvitation(user.id),
                          (u) =>
                            t('access.users.notice.resent', {email: u.email}),
                        )
                      }
                    >
                      {t('access.users.actions.resend')}
                    </ActionButton>
                  )}
                  {!user.is_you &&
                    (user.status === 'deactivated' ? (
                      <ActionButton
                        action="confirm"
                        busy={busyId === user.id}
                        onClick={() =>
                          void act(
                            user,
                            () => setUserActive(user.id, true),
                            (u) =>
                              t('access.users.notice.reactivated', {
                                name: nameOf(u),
                              }),
                          )
                        }
                      >
                        {t('access.users.actions.reactivate')}
                      </ActionButton>
                    ) : (
                      <ActionButton
                        action="danger"
                        onClick={() => setDeactivating(user)}
                      >
                        {t('access.users.actions.deactivate')}
                      </ActionButton>
                    ))}
                </Actions>
              </Row>
            )}
          />
        </>
      )}

      {inviting && (
        <InviteUserModal
          defaultRole={inviting}
          onClose={() => setInviting(null)}
          onInvited={(user) => {
            setInviting(null);
            setNotice({
              kind: 'success',
              text: t('access.users.notice.invited', {email: user.email}),
            });
            load();
          }}
        />
      )}
      {changing && (
        <ChangeRoleModal
          user={changing}
          name={nameOf(changing)}
          onClose={() => setChanging(null)}
          onChanged={(user) => {
            setChanging(null);
            setNotice({
              kind: 'success',
              text: t('access.users.notice.roleChanged', {
                name: nameOf(user),
                role: t(`access.roles.${user.role}`),
              }),
            });
            load();
          }}
        />
      )}
      {deactivating && (
        <Modal
          title={t('access.users.confirmDeactivate.title', {
            name: nameOf(deactivating),
          })}
          onClose={() => setDeactivating(null)}
        >
          <p>{t('access.users.confirmDeactivate.body')}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setDeactivating(null)}>
              {t('common.cancel')}
            </Button>
            <ActionButton
              action="danger"
              size="md"
              busy={busyId === deactivating.id}
              onClick={() =>
                void act(
                  deactivating,
                  () => setUserActive(deactivating.id, false),
                  (u) =>
                    t('access.users.notice.deactivated', {name: nameOf(u)}),
                )
              }
            >
              {t('access.users.actions.deactivate')}
            </ActionButton>
          </div>
        </Modal>
      )}
    </>
  );
}

/** The last sign-in, or for an invitee until when their link works. */
function LastSeen({user}: {user: CompanyUser}) {
  const {t} = useTranslation();
  if (user.status === 'invited') {
    const until = user.invitation_expires_at;
    return until && new Date(until) > new Date() ? (
      <span className="muted">
        {t('access.users.invitationUntil', {date: formatDay(until)})}
      </span>
    ) : (
      <span className="muted">{t('access.users.invitationExpired')}</span>
    );
  }
  return user.last_sign_in_at ? (
    <>{formatDateTime(user.last_sign_in_at)}</>
  ) : (
    <span className="muted">{t('access.users.never')}</span>
  );
}
