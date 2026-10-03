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
  createTax,
  deleteTax,
  listTaxes,
  setTaxActive,
  updateTax,
  type NewTaxPayload,
  type Tax,
  type TaxPayload,
} from '../api/taxSettingsApi';
import {describeRate, describeValidity} from './format';
import {TaxForm} from './TaxForm';

type Editing = Tax | 'new' | null;

/** Configuración › Impuestos: the company's taxes and retenciones. The owner and the accountant edit; everyone reads. */
export function TaxSettings() {
  const {t} = useTranslation();
  const {session} = useSession();
  const canEdit = session?.role === 'owner' || session?.role === 'accountant';
  const [taxes, setTaxes] = useState<Tax[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [filter, setFilter] = useState('all');
  const [editing, setEditing] = useState<Editing>(null);
  const [removing, setRemoving] = useState<Tax | null>(null);
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
    listTaxes().then(
      (items) => {
        if (!current) return;
        setTaxes(items);
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
    const known = [
      'tax_in_use',
      'tax_not_editable',
      'forbidden',
      'tax_not_found',
    ];
    return known.includes(code)
      ? t(`taxes.errors.${code}`)
      : t('common.errors.unexpected');
  };

  const save = async (payload: NewTaxPayload | TaxPayload) => {
    if (editing === 'new') {
      await createTax(payload as NewTaxPayload);
      setNotice({kind: 'success', text: t('taxes.notice.created')});
    } else if (editing) {
      await updateTax(editing.id, payload);
      setNotice({kind: 'success', text: t('taxes.notice.updated')});
    }
    setEditing(null);
    load();
  };

  const toggle = async (tax: Tax) => {
    setBusyId(tax.id);
    try {
      await setTaxActive(tax.id, !tax.active);
      setNotice({
        kind: 'success',
        text: t(
          tax.active ? 'taxes.notice.deactivated' : 'taxes.notice.activated',
          {name: tax.name},
        ),
      });
      load();
    } catch (error) {
      setNotice({kind: 'error', text: explain(error)});
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (tax: Tax) => {
    setBusyId(tax.id);
    try {
      await deleteTax(tax.id);
      setNotice({
        kind: 'success',
        text: t('taxes.notice.deleted', {name: tax.name}),
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
  if (taxes === null) return <Loading />;

  const shown = taxes.filter(
    (tax) => filter === 'all' || tax.tax_class === filter,
  );

  return (
    <>
      <TabIntro>{t('taxes.intro')}</TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <FilterBar
        filters={[
          {
            name: 'class',
            label: t('taxes.filters.class'),
            value: filter,
            onChange: setFilter,
            options: [
              {value: 'all', label: t('taxes.filters.all')},
              {value: 'charge', label: t('taxes.filters.charge')},
              {value: 'withholding', label: t('taxes.filters.withholding')},
            ],
          },
        ]}
      >
        {canEdit && (
          <Button onClick={() => setEditing('new')}>{t('taxes.new')}</Button>
        )}
      </FilterBar>
      {shown.length === 0 ? (
        <EmptyState>{t('taxes.empty')}</EmptyState>
      ) : (
        <>
          <RowLegend
            statuses={[{value: 'cancelled', label: t('taxes.status.inactive')}]}
          />
          <DataTable
            actions={canEdit}
            columns={[
              t('taxes.columns.name'),
              t('taxes.columns.type'),
              t('taxes.columns.rate'),
              t('taxes.columns.validity'),
              t('taxes.columns.salesAccount'),
              t('taxes.columns.purchaseAccount'),
            ]}
            rows={shown}
            renderRow={(tax) => {
              const validity = describeValidity(tax);
              const fixed = tax.kind === 'none';
              return (
                <Row
                  key={tax.id}
                  status={tax.active ? null : 'cancelled'}
                  label={tax.active ? null : t('taxes.status.inactive')}
                >
                  <td>{tax.name}</td>
                  <td>
                    {t(`taxes.class.${tax.tax_class}`)} ·{' '}
                    {t(`taxes.kind.${tax.kind}`)}
                  </td>
                  <td>{fixed ? '' : describeRate(tax)}</td>
                  <td>
                    {fixed
                      ? ''
                      : t(`taxes.validity.${validity.key}`, validity.params)}
                  </td>
                  <td>{tax.sales_account_code ?? ''}</td>
                  <td>{tax.purchase_account_code ?? ''}</td>
                  {canEdit && (
                    <Actions>
                      {!fixed && (
                        <>
                          <ActionButton
                            action="edit"
                            onClick={() => setEditing(tax)}
                          >
                            {t('taxes.actions.edit')}
                          </ActionButton>
                          <ActionButton
                            action={tax.active ? 'danger' : 'confirm'}
                            busy={busyId === tax.id}
                            onClick={() => void toggle(tax)}
                          >
                            {tax.active
                              ? t('taxes.actions.deactivate')
                              : t('taxes.actions.activate')}
                          </ActionButton>
                          {!tax.in_use && (
                            <ActionButton
                              action="danger"
                              onClick={() => setRemoving(tax)}
                            >
                              {t('taxes.actions.delete')}
                            </ActionButton>
                          )}
                        </>
                      )}
                    </Actions>
                  )}
                </Row>
              );
            }}
          />
        </>
      )}
      {editing && (
        <TaxForm
          tax={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSave={save}
        />
      )}
      {removing && (
        <Modal
          title={t('taxes.confirmDelete.title')}
          onClose={() => setRemoving(null)}
        >
          <p>{t('taxes.confirmDelete.body', {name: removing.name})}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setRemoving(null)}>
              {t('common.cancel')}
            </Button>
            <Button
              busy={busyId === removing.id}
              onClick={() => void remove(removing)}
            >
              {t('taxes.actions.delete')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
