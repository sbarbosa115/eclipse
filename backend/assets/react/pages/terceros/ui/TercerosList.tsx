import {useCallback, useEffect, useState} from 'react';
import {Link, useLocation, useSearchParams} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {
  deactivateTercero,
  deleteTercero,
  identificationLabel,
  reactivateTercero,
  RoleBadges,
  searchTerceros,
  terceroErrorMessage,
  type Page,
  type Role,
  type TerceroSummary,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {
  actionClass,
  ActionButton,
  Actions,
  Alert,
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  PageHeader,
  Pager,
  Row,
  RowLegend,
} from '@/shared/ui';
import {ConfirmModal} from './ConfirmModal';

const PER_PAGE = 25;

type Result =
  | {key: string; status: 'ok'; data: Page<TerceroSummary>}
  | {key: string; status: 'failed'};

type Pending = {kind: 'delete' | 'deactivate'; tercero: TerceroSummary};

/** Who may create, edit, deactivate and delete: the owner and billing users (the accountant reads). */
export const canWriteTerceros = (role: string | undefined) =>
  role === 'owner' || role === 'billing';

export function TercerosList() {
  const {t} = useTranslation();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWriteTerceros(session?.role);
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const role = (params.get('role') ?? '') as Role | '';
  const active = (params.get('active') ?? '') as '1' | '0' | '';
  const page = Math.max(1, Number(params.get('page')) || 1);

  const [result, setResult] = useState<Result | null>(null);
  const [reloads, setReloads] = useState(0);
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [pending, setPending] = useState<Pending | null>(null);
  const [rowFailure, setRowFailure] = useState<string | null>(null);

  const key = `${q}|${role}|${active}|${page}|${reloads}`;
  useEffect(() => {
    let cancelled = false;
    searchTerceros({q, role, active, page, per_page: PER_PAGE})
      .then((data) => !cancelled && setResult({key, status: 'ok', data}))
      .catch(() => !cancelled && setResult({key, status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [key, q, role, active, page]);

  const reload = useCallback(() => setReloads((n) => n + 1), []);

  const setFilter = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(name, value);
    else next.delete(name);
    if (name !== 'page') next.delete('page');
    setParams(next, {replace: true});
  };
  const showAll = () => setParams(new URLSearchParams(), {replace: true});

  const filtered = q !== '' || role !== '' || active !== '';
  // Keep showing the last answer, dimmed, while the next one is on its way.
  const shown = result?.status === 'ok' ? result.data : null;
  const busy = result?.key !== key;
  const toggleActive = async (tercero: TerceroSummary) => {
    setRowFailure(null);
    try {
      if (tercero.active) await deactivateTercero(tercero.id);
      else await reactivateTercero(tercero.id);
      reload();
    } catch (error) {
      setRowFailure(terceroErrorMessage(error, t));
    }
  };

  return (
    <>
      <PageHeader
        title={t('terceros.title')}
        subtitle={t('terceros.subtitle')}
        actions={
          writer && (
            <Link to="nuevo" className="btn btn-primary">
              {t('terceros.new')}
            </Link>
          )
        }
      />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setRowFailure(null)}>
        {rowFailure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => setFilter('q', value.trim())}
        searchPlaceholder={t('terceros.list.searchPlaceholder')}
        filters={[
          {
            name: 'role',
            label: t('terceros.list.roleFilter'),
            value: role,
            onChange: (value) => setFilter('role', value),
            options: [
              {value: '', label: t('terceros.list.allRoles')},
              ...(['cliente', 'proveedor', 'empleado', 'otro'] as const).map(
                (r) => ({value: r, label: t(`terceros.roles.${r}`)}),
              ),
            ],
          },
          {
            name: 'active',
            label: t('terceros.list.statusFilter'),
            value: active,
            onChange: (value) => setFilter('active', value),
            options: [
              {value: '', label: t('terceros.list.allStatuses')},
              {value: '1', label: t('terceros.list.onlyActive')},
              {value: '0', label: t('terceros.list.onlyInactive')},
            ],
          },
        ]}
      />
      {result === null && <Loading />}
      {result?.status === 'failed' && !busy && (
        <ErrorState message={t('common.loadFailed')} onRetry={reload} />
      )}
      {shown !== null &&
        (shown.items.length === 0 ? (
          filtered ? (
            <EmptyState
              action={
                <Button variant="ghost" onClick={showAll}>
                  {t('common.showAll')}
                </Button>
              }
            >
              {t('terceros.list.filteredEmpty')}
            </EmptyState>
          ) : (
            <EmptyState
              action={
                writer && (
                  <Link to="nuevo" className="btn btn-primary">
                    {t('terceros.list.emptyAction')}
                  </Link>
                )
              }
            >
              {t('terceros.list.empty')}
            </EmptyState>
          )
        ) : (
          <>
            <RowLegend
              statuses={[
                {value: 'cancelled', label: t('terceros.list.inactive')},
              ]}
            />
            <DataTable
              columns={[
                t('terceros.list.columns.name'),
                t('terceros.list.columns.identification'),
                t('terceros.list.columns.roles'),
                t('terceros.list.columns.email'),
                t('terceros.list.columns.city'),
              ]}
              rows={shown.items}
              busy={busy}
              renderRow={(tercero) => (
                <Row
                  key={tercero.id}
                  status={tercero.active ? null : 'cancelled'}
                  label={tercero.active ? null : t('terceros.list.inactive')}
                >
                  <td>
                    <Link to={tercero.id}>{tercero.display_name}</Link>
                    {tercero.trade_name && (
                      <span className="muted small">
                        {' '}
                        · {tercero.trade_name}
                      </span>
                    )}
                  </td>
                  <td>
                    {identificationLabel(tercero)}
                    {tercero.branch_code !== '0' && (
                      <span className="muted small">
                        {' '}
                        ·{' '}
                        {t('terceros.list.branch', {code: tercero.branch_code})}
                      </span>
                    )}
                  </td>
                  <td>
                    <RoleBadges roles={tercero.roles} />
                  </td>
                  <td>{tercero.email}</td>
                  <td>{tercero.city}</td>
                  <Actions>
                    <Link
                      to={tercero.id}
                      className={actionClass(writer ? 'edit' : 'open')}
                    >
                      {t(
                        writer
                          ? 'terceros.actions.edit'
                          : 'terceros.actions.view',
                      )}
                    </Link>
                    {writer && (
                      <>
                        {tercero.active ? (
                          <ActionButton
                            action="danger"
                            onClick={() =>
                              setPending({kind: 'deactivate', tercero})
                            }
                          >
                            {t('terceros.actions.deactivate')}
                          </ActionButton>
                        ) : (
                          <ActionButton
                            action="confirm"
                            onClick={() => toggleActive(tercero)}
                          >
                            {t('terceros.actions.reactivate')}
                          </ActionButton>
                        )}
                        <ActionButton
                          action="danger"
                          onClick={() => setPending({kind: 'delete', tercero})}
                        >
                          {t('terceros.actions.delete')}
                        </ActionButton>
                      </>
                    )}
                  </Actions>
                </Row>
              )}
            />
            <Pager data={shown} onPage={(p) => setFilter('page', String(p))} />
          </>
        ))}
      {pending?.kind === 'delete' && (
        <ConfirmModal
          title={t('terceros.confirm.deleteTitle')}
          body={t('terceros.confirm.deleteBody', {
            name: pending.tercero.display_name,
          })}
          confirmLabel={t('terceros.confirm.deleteConfirm')}
          onClose={() => setPending(null)}
          onConfirm={async () => {
            try {
              await deleteTercero(pending.tercero.id);
            } catch (error) {
              throw new Error(terceroErrorMessage(error, t));
            }
            setPending(null);
            reload();
          }}
        />
      )}
      {pending?.kind === 'deactivate' && (
        <ConfirmModal
          title={t('terceros.confirm.deactivateTitle')}
          body={t('terceros.confirm.deactivateBody', {
            name: pending.tercero.display_name,
          })}
          confirmLabel={t('terceros.confirm.deactivateConfirm')}
          onClose={() => setPending(null)}
          onConfirm={async () => {
            try {
              await deactivateTercero(pending.tercero.id);
            } catch (error) {
              throw new Error(terceroErrorMessage(error, t));
            }
            setPending(null);
            reload();
          }}
        />
      )}
    </>
  );
}
