import {useCallback, useEffect, useState} from 'react';
import {useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {
  ActionButton,
  Actions,
  Alert,
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
  createPaymentMethod,
  deletePaymentMethod,
  listPaymentMethods,
  setPaymentMethodActive,
  updatePaymentMethod,
  type NewPaymentMethodPayload,
  type PaymentMethod,
  type PaymentMethodPayload,
} from '../api/paymentMethodSettingsApi';
import {PaymentMethodForm} from './PaymentMethodForm';

type Editing = PaymentMethod | 'new' | null;

const KNOWN_ERRORS = [
  'payment_method_in_use',
  'forbidden',
  'payment_method_not_found',
];

/** Configuración › Formas de pago. The owner and the accountant edit; everyone reads. */
export function PaymentMethodSettings() {
  const {t} = useTranslation();
  const {session} = useSession();
  const canEdit = session?.role === 'owner' || session?.role === 'accountant';
  const [methods, setMethods] = useState<PaymentMethod[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [filter, setFilter] = useState('all');
  const [editing, setEditing] = useState<Editing>(null);
  const [removing, setRemoving] = useState<PaymentMethod | null>(null);
  const [notice, setNotice] = useState<{
    kind: 'success' | 'error';
    text: string;
  } | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  // Bumped to read the list again (after a change, or "Reintentar").
  const [reads, setReads] = useState(0);
  const load = useCallback(() => setReads((n) => n + 1), []);
  useEffect(() => {
    let current = true;
    listPaymentMethods().then(
      (items) => {
        if (!current) return;
        setMethods(items);
        setFailed(false);
      },
      () => current && setFailed(true),
    );
    return () => {
      current = false;
    };
  }, [reads]);

  const explain = (error: unknown): string => {
    const code = error instanceof ApiError ? error.code : '';
    return KNOWN_ERRORS.includes(code)
      ? t(`paymentMethods.errors.${code}`)
      : t('common.errors.unexpected');
  };

  const save = async (
    payload: NewPaymentMethodPayload | PaymentMethodPayload,
  ) => {
    if (editing === 'new') {
      await createPaymentMethod(payload as NewPaymentMethodPayload);
      setNotice({kind: 'success', text: t('paymentMethods.notice.created')});
    } else if (editing) {
      await updatePaymentMethod(editing.id, payload);
      setNotice({kind: 'success', text: t('paymentMethods.notice.updated')});
    }
    setEditing(null);
    load();
  };

  const toggle = async (method: PaymentMethod) => {
    setBusyId(method.id);
    try {
      await setPaymentMethodActive(method.id, !method.active);
      setNotice({
        kind: 'success',
        text: t(
          method.active
            ? 'paymentMethods.notice.deactivated'
            : 'paymentMethods.notice.activated',
          {name: method.name},
        ),
      });
      load();
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (method: PaymentMethod) => {
    setBusyId(method.id);
    try {
      await deletePaymentMethod(method.id);
      setNotice({
        kind: 'success',
        text: t('paymentMethods.notice.deleted', {name: method.name}),
      });
      load();
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setBusyId(null);
      setRemoving(null);
    }
  };

  if (failed)
    return <ErrorState message={t('common.loadFailed')} onRetry={load} />;
  if (methods === null) return <Loading />;

  const shown = methods.filter(
    (method) => filter === 'all' || method.kind === filter,
  );

  return (
    <>
      <TabIntro>{t('paymentMethods.intro')}</TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <FilterBar
        filters={[
          {
            name: 'kind',
            label: t('paymentMethods.filters.kind'),
            value: filter,
            onChange: setFilter,
            options: [
              {value: 'all', label: t('paymentMethods.filters.all')},
              {value: 'cash', label: t('paymentMethods.kind.cash')},
              {value: 'credit', label: t('paymentMethods.kind.credit')},
            ],
          },
        ]}
      >
        {canEdit && (
          <Button onClick={() => setEditing('new')}>
            {t('paymentMethods.new')}
          </Button>
        )}
      </FilterBar>
      {shown.length === 0 ? (
        <EmptyState>{t('paymentMethods.empty')}</EmptyState>
      ) : (
        <>
          <RowLegend
            statuses={[
              {value: 'cancelled', label: t('paymentMethods.status.inactive')},
            ]}
          />
          <DataTable
            actions={canEdit}
            columns={[
              t('paymentMethods.columns.name'),
              t('paymentMethods.columns.kind'),
              t('paymentMethods.columns.account'),
            ]}
            rows={shown}
            renderRow={(method) => (
              <Row
                key={method.id}
                status={method.active ? null : 'cancelled'}
                label={
                  method.active ? null : t('paymentMethods.status.inactive')
                }
              >
                <td>{method.name}</td>
                <td>{t(`paymentMethods.kind.${method.kind}`)}</td>
                <td>
                  {method.account_code
                    ? `${method.account_code} · ${method.account_name ?? ''}`
                    : t('paymentMethods.tercerosAccount')}
                </td>
                {canEdit && (
                  <Actions>
                    <ActionButton
                      action="edit"
                      onClick={() => setEditing(method)}
                    >
                      {t('paymentMethods.actions.edit')}
                    </ActionButton>
                    <ActionButton
                      action={method.active ? 'danger' : 'confirm'}
                      busy={busyId === method.id}
                      onClick={() => void toggle(method)}
                    >
                      {method.active
                        ? t('paymentMethods.actions.deactivate')
                        : t('paymentMethods.actions.activate')}
                    </ActionButton>
                    {!method.in_use && (
                      <ActionButton
                        action="danger"
                        onClick={() => setRemoving(method)}
                      >
                        {t('paymentMethods.actions.delete')}
                      </ActionButton>
                    )}
                  </Actions>
                )}
              </Row>
            )}
          />
        </>
      )}
      {editing && (
        <PaymentMethodForm
          method={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSave={save}
        />
      )}
      {removing && (
        <Modal
          title={t('paymentMethods.confirmDelete.title')}
          onClose={() => setRemoving(null)}
        >
          <p>{t('paymentMethods.confirmDelete.body', {name: removing.name})}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setRemoving(null)}>
              {t('common.cancel')}
            </Button>
            <Button
              busy={busyId === removing.id}
              onClick={() => void remove(removing)}
            >
              {t('paymentMethods.actions.delete')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
