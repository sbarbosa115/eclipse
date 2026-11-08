import {useEffect, useState} from 'react';
import {can, useSession} from '@/entities/session';
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
  TabIntro,
} from '@/shared/ui';
import {
  fetchConcepts,
  replaceConcepts,
  type ExogenaConcept,
} from '../api/exogenaConceptsApi';
import {sameRow, sorted} from '../lib/table';
import {ConceptModal} from './ConceptModal';

type Editing = ExogenaConcept | 'new' | null;

/**
 * Configuración › Exógena (observations PRD §4.7 d): which DIAN concept each family of accounts reports under in
 * formats 1001 (pagos) and 1007 (ingresos); an account uses the row with the longest prefix that covers it. The owner
 * and the accountant edit it; every save sends the whole table, which the server replaces and logs.
 */
export function ExogenaConcepts() {
  const {t} = useTranslation();
  const {session} = useSession();
  const keeper = can(session, 'MANAGE_BOOKS');
  const [rows, setRows] = useState<ExogenaConcept[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [editing, setEditing] = useState<Editing>(null);
  const [removing, setRemoving] = useState<ExogenaConcept | null>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<{
    kind: 'success' | 'error';
    text: string;
  } | null>(null);

  useEffect(() => {
    if (!keeper) return;
    let cancelled = false;
    fetchConcepts()
      .then(({items}) => {
        if (cancelled) return;
        setRows(items);
        setFailed(false);
      })
      .catch(() => !cancelled && setFailed(true));
    return () => {
      cancelled = true;
    };
  }, [keeper, attempt]);

  const label = (row: ExogenaConcept) =>
    t('ledger.exogena.rowLabel', {
      format: row.format,
      prefix: row.account_prefix,
    });

  const save = async (next: ExogenaConcept[]) => {
    const {items} = await replaceConcepts(sorted(next));
    setRows(items);
  };

  const remove = async (row: ExogenaConcept) => {
    setBusy(true);
    try {
      await save((rows ?? []).filter((r) => !sameRow(r, row)));
      setNotice({
        kind: 'success',
        text: t('ledger.exogena.notice.removed', {row: label(row)}),
      });
    } catch {
      setNotice({kind: 'error', text: t('common.errors.unexpected')});
    } finally {
      setBusy(false);
      setRemoving(null);
    }
  };

  if (!session) return null;
  if (!keeper) {
    return <EmptyState>{t('ledger.exogena.noAccess')}</EmptyState>;
  }
  if (failed) {
    return (
      <ErrorState
        message={t('common.loadFailed')}
        onRetry={() => setAttempt((n) => n + 1)}
      />
    );
  }
  if (rows === null) return <Loading />;

  const others =
    editing === null || editing === 'new'
      ? rows
      : rows.filter((r) => !sameRow(r, editing));

  return (
    <>
      <TabIntro>{t('ledger.exogena.intro')}</TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <FilterBar>
        <Button onClick={() => setEditing('new')}>
          {t('ledger.exogena.new')}
        </Button>
      </FilterBar>
      {rows.length === 0 ? (
        <EmptyState>{t('ledger.exogena.empty')}</EmptyState>
      ) : (
        <DataTable
          columns={[
            t('ledger.exogena.columns.format'),
            t('ledger.exogena.columns.prefix'),
            t('ledger.exogena.columns.concept'),
          ]}
          rows={rows}
          renderRow={(row) => (
            <tr key={`${row.format}-${row.account_prefix}`}>
              <td>{t(`ledger.exogena.formats.${row.format}`)}</td>
              <td>{row.account_prefix}</td>
              <td>{row.concept_code}</td>
              <Actions>
                <ActionButton
                  action="edit"
                  onClick={() => setEditing(row)}
                >
                  {t('ledger.exogena.edit')}
                </ActionButton>
                <ActionButton
                  action="danger"
                  onClick={() => setRemoving(row)}
                >
                  {t('ledger.exogena.remove')}
                </ActionButton>
              </Actions>
            </tr>
          )}
        />
      )}
      {editing && (
        <ConceptModal
          row={editing === 'new' ? null : editing}
          others={others}
          onClose={() => setEditing(null)}
          onSave={async (row) => {
            await save([...others, row]);
            setEditing(null);
            setNotice({kind: 'success', text: t('ledger.exogena.notice.saved')});
          }}
        />
      )}
      {removing && (
        <Modal
          title={t('ledger.exogena.confirmRemove.title')}
          onClose={() => setRemoving(null)}
        >
          <p>
            {t('ledger.exogena.confirmRemove.body', {row: label(removing)})}
          </p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setRemoving(null)}>
              {t('common.cancel')}
            </Button>
            <Button busy={busy} onClick={() => void remove(removing)}>
              {t('ledger.exogena.remove')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
