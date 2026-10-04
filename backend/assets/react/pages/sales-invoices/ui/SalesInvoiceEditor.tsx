import {useEffect, useMemo, useState} from 'react';
import {Link, useLocation, useNavigate, useParams} from 'react-router-dom';
import {listTaxes, type Tax} from '@/entities/product';
import {
  canSend,
  canVoid,
  createSalesInvoice,
  duplicateSalesInvoice,
  emitSalesInvoice,
  getResolutionSettings,
  getSalesInvoice,
  salesInvoiceErrorMessage,
  salesInvoicePdfUrl,
  sendSalesInvoice,
  updateSalesInvoice,
  voidSalesInvoice,
  type ResolutionSettings,
  type SalesInvoice,
} from '@/entities/sales-invoice';
import {useSession} from '@/entities/session';
import {EmitDocument} from '@/features/emit-document';
import {VoidDocumentModal} from '@/features/void-document';
import {ApiError} from '@/shared/api';
import {useTranslation, type Translate} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {
  actionClass,
  ActionButton,
  Alert,
  Button,
  ErrorState,
  Loading,
  PageHeader,
} from '@/shared/ui';
import {
  computeTotals,
  DocumentEditor,
  emptyDraft,
  hasErrors,
  validateDraft,
  type DocumentDraft,
  type EditorErrors,
} from '@/widgets/document-editor';
import {listPaymentMethods, type PaymentMethod} from '../api/options';
import {
  draftFromInvoice,
  editorErrorsFrom,
  requestFromDraft,
} from '../model/draftMapping';
import {canWriteSalesInvoices} from '../model/access';

/** Tipo (§4.6): the invoice's numbering series, the resolution's prefix. */
function typeLabelFor(t: Translate, prefix: string | null | undefined): string {
  return prefix
    ? t('salesInvoice.typeLabelWithPrefix', {prefix})
    : t('salesInvoice.typeLabel');
}

type Load =
  {status: 'loading'} | {status: 'failed'; message: string} | {status: 'ready'};

/**
 * One factura de venta (§4.6, §4.8): a new or draft one is edited in the shared document form and saved, emitted or
 * emitted and sent; an emitted one is shown read-only, with its PDF, duplicate, send and void (§4.12, §4.15).
 */
export function SalesInvoiceEditor() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const {id: routeId} = useParams();
  const {session} = useSession();
  const writer = canWriteSalesInvoices(session);

  const [id, setId] = useState<string | null>(routeId ?? null);
  const [invoice, setInvoice] = useState<SalesInvoice | null>(null);
  const [draft, setDraft] = useState<DocumentDraft>(() =>
    emptyDraft({typeLabel: t('salesInvoice.typeLabel')}),
  );
  const [load, setLoad] = useState<Load>(
    routeId ? {status: 'loading'} : {status: 'ready'},
  );
  const [resolution, setResolution] = useState<ResolutionSettings | null>(null);
  const [taxes, setTaxes] = useState<Tax[]>([]);
  const [methods, setMethods] = useState<PaymentMethod[]>([]);
  const [errors, setErrors] = useState<EditorErrors>({});
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [voiding, setVoiding] = useState(false);

  const typeLabel = (prefix: string | null | undefined) =>
    typeLabelFor(t, prefix);

  // The invoice (when it exists), the resolution (Tipo and its warning), and what the form checks with.
  useEffect(() => {
    let cancelled = false;
    Promise.all([listTaxes('charge'), listTaxes('withholding')])
      .then(([charge, withholding]) => {
        if (!cancelled) setTaxes([...charge, ...withholding]);
      })
      .catch(() => {});
    listPaymentMethods()
      .then((items) => !cancelled && setMethods(items))
      .catch(() => {});
    getResolutionSettings()
      .then((settings) => {
        if (cancelled) return;
        setResolution(settings);
        if (!routeId) {
          setDraft((d) => ({
            ...d,
            type_label: typeLabelFor(t, settings.resolution?.prefix),
          }));
        }
      })
      .catch(() => {});
    if (routeId) {
      getSalesInvoice(routeId)
        .then((found) => {
          if (cancelled) return;
          setInvoice(found);
          setDraft(draftFromInvoice(found, typeLabelFor(t, found.prefix)));
          setLoad({status: 'ready'});
        })
        .catch((error) => {
          if (!cancelled) {
            setLoad({
              status: 'failed',
              message:
                error instanceof ApiError && error.status === 404
                  ? salesInvoiceErrorMessage(error, t)
                  : t('salesInvoice.editor.loadFailed'),
            });
          }
        });
    }
    return () => {
      cancelled = true;
    };
  }, [routeId, t]);

  const net = useMemo(
    () => computeTotals(draft.lines, taxes).net,
    [draft.lines, taxes],
  );

  const isDraft = invoice === null || invoice.status === 'draft';
  const editable = writer && isDraft;

  const check = (forEmission: boolean): boolean => {
    const found = validateDraft('sales_invoice', draft, {
      net,
      methods,
      forEmission,
      t,
    });
    setErrors(found);
    if (hasErrors(found)) {
      setFailure(t('salesInvoice.errors.validation_failed'));
      return false;
    }
    return true;
  };

  const show = (saved: SalesInvoice) => {
    setInvoice(saved);
    setDraft(draftFromInvoice(saved, typeLabel(saved.prefix)));
    setErrors({});
  };

  /** Saves the draft; rejects with the message to show (and puts the fields' problems on the form). */
  const save = async (): Promise<SalesInvoice> => {
    const {body, lineIndexes} = requestFromDraft(
      draft,
      invoice?.seller_id ?? null,
    );
    try {
      const saved = id
        ? await updateSalesInvoice(id, body)
        : await createSalesInvoice(body);
      setId(saved.id);
      return saved;
    } catch (error) {
      const fields = editorErrorsFrom(error, lineIndexes);
      if (fields) setErrors(fields);
      throw new Error(salesInvoiceErrorMessage(error, t));
    }
  };

  const onSave = async () => {
    setNotice(null);
    setFailure(null);
    if (!check(false)) return;
    setBusy('save');
    try {
      const saved = await save();
      if (!routeId) {
        navigate(`../${saved.id}`, {
          replace: true,
          relative: 'path',
          state: {notice: t('salesInvoice.notices.saved')},
        });
        return;
      }
      show(saved);
      setNotice(t('salesInvoice.notices.saved'));
    } catch (error) {
      setFailure((error as Error).message);
    } finally {
      setBusy(null);
    }
  };

  const onEmit = async (send: boolean) => {
    setNotice(null);
    setFailure(null);
    const saved = await save();
    let emitted: SalesInvoice;
    try {
      emitted = await emitSalesInvoice(saved.id, send);
    } catch (error) {
      const fields = editorErrorsFrom(
        error,
        requestFromDraft(draft).lineIndexes,
      );
      if (fields) setErrors(fields);
      throw new Error(salesInvoiceErrorMessage(error, t));
    }
    const message = t(
      send
        ? 'salesInvoice.notices.emittedAndSent'
        : 'salesInvoice.notices.emitted',
      {number: emitted.number ?? ''},
    );
    if (!routeId) {
      navigate(`../${emitted.id}`, {
        replace: true,
        relative: 'path',
        state: {notice: message},
      });
      return;
    }
    show(emitted);
    setNotice(message);
  };

  const act = async (name: string, work: () => Promise<void>) => {
    setNotice(null);
    setFailure(null);
    setBusy(name);
    try {
      await work();
    } catch (error) {
      setFailure(salesInvoiceErrorMessage(error, t));
    } finally {
      setBusy(null);
    }
  };

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <>
        <PageHeader
          title={t('salesInvoice.title')}
          actions={
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('salesInvoice.actions.back')}
            </Link>
          }
        />
        <ErrorState message={load.message} />
      </>
    );
  }

  const title = invoice?.number
    ? t('salesInvoice.editor.title', {number: invoice.number})
    : invoice
      ? t('salesInvoice.editor.draftTitle')
      : t('salesInvoice.editor.newTitle');
  const status = resolution?.status;

  return (
    <>
      <PageHeader
        title={title}
        subtitle={
          invoice ? t(`salesInvoice.statuses.${invoice.status}`) : undefined
        }
        actions={
          <>
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('salesInvoice.actions.back')}
            </Link>
            {invoice && (
              <a
                href={salesInvoicePdfUrl(invoice.id)}
                target="_blank"
                rel="noreferrer"
                className={actionClass('open', '', 'md')}
              >
                {t('salesInvoice.actions.downloadPdf')}
              </a>
            )}
            {editable && (
              <>
                <Button
                  variant="secondary"
                  busy={busy === 'save'}
                  onClick={onSave}
                >
                  {t('salesInvoice.actions.save')}
                </Button>
                <EmitDocument
                  emitLabel={t('salesInvoice.actions.emit')}
                  emitAndSendLabel={t('salesInvoice.actions.emitAndSend')}
                  confirmTitle={t('salesInvoice.emit.title')}
                  confirmBody={t('salesInvoice.emit.body')}
                  sendNote={t('salesInvoice.emit.sendNote')}
                  disabled={busy !== null}
                  beforeConfirm={() => {
                    setNotice(null);
                    setFailure(null);
                    return check(true);
                  }}
                  onEmit={onEmit}
                />
              </>
            )}
            {writer && invoice && !isDraft && (
              <>
                <ActionButton
                  action="setup"
                  size="md"
                  busy={busy === 'duplicate'}
                  onClick={() =>
                    act('duplicate', async () => {
                      const copy = await duplicateSalesInvoice(invoice.id);
                      navigate(`../${copy.id}`, {
                        relative: 'path',
                        state: {notice: t('salesInvoice.notices.duplicated')},
                      });
                    })
                  }
                >
                  {t('salesInvoice.actions.duplicate')}
                </ActionButton>
                {canSend(invoice) && (
                  <ActionButton
                    action="confirm"
                    size="md"
                    busy={busy === 'send'}
                    onClick={() =>
                      act('send', async () => {
                        await sendSalesInvoice(invoice.id);
                        setNotice(
                          t('salesInvoice.notices.sent', {
                            number: invoice.number ?? '',
                          }),
                        );
                      })
                    }
                  >
                    {t('salesInvoice.actions.send')}
                  </ActionButton>
                )}
                {canVoid(invoice) && (
                  <ActionButton
                    action="danger"
                    size="md"
                    onClick={() => setVoiding(true)}
                  >
                    {t('salesInvoice.actions.void')}
                  </ActionButton>
                )}
              </>
            )}
          </>
        }
      />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      {editable && status && status.status === 'missing' && (
        <Alert kind="warning">{t('salesInvoice.resolution.missing')}</Alert>
      )}
      {editable &&
        status &&
        status.status !== 'missing' &&
        status.status !== 'active' && (
          <Alert kind="warning">
            {t('salesInvoice.resolution.inactive', {
              status: t(`salesInvoice.resolution.statuses.${status.status}`),
            })}
          </Alert>
        )}
      {editable && status?.warning && (
        <Alert kind="warning">
          {t('salesInvoice.resolution.warning', {
            numbers: String(status.numbers_left),
            days: String(status.days_left),
          })}
        </Alert>
      )}
      {invoice?.status === 'voided' && (
        <Alert kind="info">
          {t('salesInvoice.editor.voidedInfo', {
            date: formatDate(invoice.voided_at),
            reason: invoice.void_reason ?? '',
          })}
        </Alert>
      )}
      {invoice && !isDraft && invoice.status !== 'voided' && (
        <Alert kind="info">
          {t('salesInvoice.editor.readOnly')}{' '}
          {Number(invoice.balance) > 0 &&
            t('salesInvoice.editor.balance', {
              amount: formatMoney(invoice.balance),
            })}
        </Alert>
      )}
      <DocumentEditor
        kind="sales_invoice"
        value={draft}
        onChange={setDraft}
        readOnly={!editable}
        errors={errors}
      />
      {voiding && invoice && (
        <VoidDocumentModal
          title={t('salesInvoice.void.title', {number: invoice.number ?? ''})}
          body={t('salesInvoice.void.body')}
          reasonLabel={t('salesInvoice.void.reason')}
          reasonRequired={t('salesInvoice.void.reasonRequired')}
          confirmLabel={t('salesInvoice.void.confirm')}
          onClose={() => setVoiding(false)}
          onVoid={async (reason) => {
            let voided: SalesInvoice;
            try {
              voided = await voidSalesInvoice(invoice.id, reason);
            } catch (error) {
              throw new Error(salesInvoiceErrorMessage(error, t));
            }
            setVoiding(false);
            show(voided);
            setNotice(
              t('salesInvoice.notices.voided', {number: voided.number ?? ''}),
            );
          }}
        />
      )}
    </>
  );
}
