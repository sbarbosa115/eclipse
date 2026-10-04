import {useEffect, useMemo, useState} from 'react';
import {Link, useLocation, useNavigate, useParams} from 'react-router-dom';
import {
  canVoid,
  canWritePurchases,
  createPurchaseInvoice,
  deletePurchaseInvoice,
  duplicatePurchaseInvoice,
  emitPurchaseInvoice,
  getPurchaseInvoice,
  purchaseErrorMessage,
  purchaseInvoicePdfUrl,
  removeSupplierFile,
  supplierFileUrl,
  updatePurchaseInvoice,
  uploadSupplierFile,
  VoidPurchaseInvoiceModal,
  type PurchaseInvoice,
} from '@/entities/purchase-invoice';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {
  actionClass,
  Alert,
  Button,
  DateInput,
  ErrorState,
  Field,
  FormModal,
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
import {
  draftFromInvoice,
  editorErrorsFrom,
  toRequest,
  type PurchaseHeader,
} from '../model/draft';
import {usePurchaseOptions} from '../model/usePurchaseOptions';

const BASE = '/facturas-compra';

type Busy = null | 'save' | 'emit' | 'attach' | 'duplicate';
type Arrival = {notice?: string; failure?: string} | null;

/**
 * A factura de compra / gasto (§4.10) on the shared document form: Proveedor, the supplier's number and due date,
 * lines by product or by expense account, formas de pago and the supplier's PDF/XML. A draft is saved and emitted
 * here; an emitted one is shown read-only with its PDF, Duplicar and Anular.
 */
export function PurchaseInvoiceEditorPage() {
  const {id} = useParams();
  const {t} = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const {session} = useSession();
  const writer = canWritePurchases(session);
  const typeLabel = t('purchaseInvoice.typeLabel');
  const arrival = location.state as Arrival;

  const [invoice, setInvoice] = useState<PurchaseInvoice | null>(null);
  const [load, setLoad] = useState<'loading' | 'ready' | 'failed'>(
    id ? 'loading' : 'ready',
  );
  const [reloads, setReloads] = useState(0);
  const [draft, setDraft] = useState<DocumentDraft>(() =>
    emptyDraft({typeLabel}),
  );
  const [header, setHeader] = useState<PurchaseHeader>({
    supplierInvoiceNumber: '',
    dueDate: '',
  });
  const [errors, setErrors] = useState<EditorErrors>({});
  const [notice, setNotice] = useState<string | null>(arrival?.notice ?? null);
  const [failure, setFailure] = useState<string | null>(
    arrival?.failure ?? null,
  );
  const [busy, setBusy] = useState<Busy>(null);
  const [dialog, setDialog] = useState<null | 'void' | 'delete'>(null);
  const options = usePurchaseOptions();

  const show = (loaded: PurchaseInvoice) => {
    const opened = draftFromInvoice(loaded, typeLabel);
    setInvoice(loaded);
    setDraft(opened.draft);
    setHeader({
      supplierInvoiceNumber: opened.supplierInvoiceNumber,
      dueDate: opened.dueDate,
    });
    setErrors({});
  };

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    getPurchaseInvoice(id)
      .then((loaded) => {
        if (cancelled) return;
        const opened = draftFromInvoice(loaded, typeLabel);
        setInvoice(loaded);
        setDraft(opened.draft);
        setHeader({
          supplierInvoiceNumber: opened.supplierInvoiceNumber,
          dueDate: opened.dueDate,
        });
        setLoad('ready');
      })
      .catch(() => !cancelled && setLoad('failed'));
    return () => {
      cancelled = true;
    };
  }, [id, reloads, typeLabel]);

  const net = useMemo(
    () => computeTotals(draft.lines, options.taxes).net,
    [draft.lines, options.taxes],
  );

  const isDraft = invoice === null || invoice.status === 'draft';
  const readOnly = !writer || !isDraft;

  /** Saves the draft (create or update) after checking it; null when it was refused, with the reasons shown. */
  const save = async (
    forEmission: boolean,
  ): Promise<PurchaseInvoice | null> => {
    setFailure(null);
    setNotice(null);
    const local = validateDraft('purchase_invoice', draft, {
      net,
      methods: options.methods,
      forEmission,
      t,
    });
    if (hasErrors(local)) {
      setErrors(local);
      setFailure(t('purchaseInvoice.notices.check'));
      return null;
    }
    const {request, lineIndexes} = toRequest(draft, header);
    try {
      const saved = invoice
        ? await updatePurchaseInvoice(invoice.id, request)
        : await createPurchaseInvoice(request);
      setErrors({});
      return saved;
    } catch (error) {
      setErrors(editorErrorsFrom(error, lineIndexes));
      setFailure(purchaseErrorMessage(error, t));
      return null;
    }
  };

  /** A new invoice moves to its own address once saved; an existing one is shown as the server answered. */
  const arrive = (saved: PurchaseInvoice, state: Arrival) => {
    if (invoice === null) {
      navigate(`${BASE}/${saved.id}`, {replace: true, state});
      return;
    }
    show(saved);
    setNotice(state?.notice ?? null);
    setFailure(state?.failure ?? null);
  };

  const onSave = async () => {
    setBusy('save');
    const saved = await save(false);
    setBusy(null);
    if (saved) arrive(saved, {notice: t('purchaseInvoice.notices.saved')});
  };

  const onEmit = async () => {
    setBusy('emit');
    const saved = await save(true);
    if (!saved) {
      setBusy(null);
      return;
    }
    try {
      const emitted = await emitPurchaseInvoice(saved.id);
      setBusy(null);
      arrive(emitted, {
        notice: t('purchaseInvoice.notices.emitted', {
          number: emitted.number ?? '',
        }),
      });
    } catch (error) {
      setBusy(null);
      if (invoice === null) {
        arrive(saved, {failure: purchaseErrorMessage(error, t)});
        return;
      }
      show(saved);
      setErrors(editorErrorsFrom(error, []));
      setFailure(purchaseErrorMessage(error, t));
    }
  };

  const onAttach = async (files: File[]) => {
    setBusy('attach');
    const target = invoice ?? (await save(false));
    if (!target) {
      setBusy(null);
      return;
    }
    let problem: string | null = null;
    for (const file of files) {
      try {
        await uploadSupplierFile(target.id, file);
      } catch (error) {
        problem = purchaseErrorMessage(error, t);
      }
    }
    setBusy(null);
    const state: Arrival = problem
      ? {failure: problem}
      : {notice: t('purchaseInvoice.notices.attached')};
    if (invoice === null) {
      arrive(target, state);
      return;
    }
    try {
      const fresh = await getPurchaseInvoice(target.id);
      // Only the files change: what is being typed in the form stays.
      setDraft((current) => ({
        ...current,
        attachments: fresh.attachments.map((f) => ({
          id: f.id,
          name: f.file_name,
          size: f.size,
        })),
      }));
      setInvoice(fresh);
    } catch {
      // The upload worked; the list refreshes on the next visit.
    }
    setNotice(state.notice ?? null);
    setFailure(state.failure ?? null);
  };

  const onRemoveAttachment = async (attachmentId: string) => {
    if (!invoice) return;
    setFailure(null);
    try {
      await removeSupplierFile(invoice.id, attachmentId);
      setDraft((current) => ({
        ...current,
        attachments: current.attachments.filter((a) => a.id !== attachmentId),
      }));
    } catch (error) {
      setFailure(purchaseErrorMessage(error, t));
    }
  };

  const onDuplicate = async () => {
    if (!invoice) return;
    setBusy('duplicate');
    try {
      const copy = await duplicatePurchaseInvoice(invoice.id);
      navigate(`${BASE}/${copy.id}`, {
        state: {notice: t('purchaseInvoice.notices.duplicated')},
      });
    } catch (error) {
      setFailure(purchaseErrorMessage(error, t));
    }
    setBusy(null);
  };

  if (load === 'loading') return <Loading />;
  if (load === 'failed') {
    return (
      <>
        <PageHeader title={t('purchaseInvoice.title')} />
        <ErrorState
          message={t('purchaseInvoice.editor.loadFailed')}
          onRetry={() => {
            setLoad('loading');
            setReloads((n) => n + 1);
          }}
        />
      </>
    );
  }

  const title =
    invoice === null
      ? t('purchaseInvoice.editor.newTitle')
      : invoice.number
        ? t('purchaseInvoice.editor.title', {number: invoice.number})
        : t('purchaseInvoice.editor.draftTitle');

  return (
    <>
      <PageHeader
        title={title}
        subtitle={
          invoice &&
          t('purchaseInvoice.editor.statusLine', {
            status: t(`purchaseInvoice.status.${invoice.status}`),
          })
        }
        actions={
          <>
            <Link to={BASE} className="btn btn-ghost">
              {t('purchaseInvoice.actions.cancel')}
            </Link>
            {invoice && (
              <a
                href={purchaseInvoicePdfUrl(invoice.id)}
                className={actionClass('open', '', 'md')}
                download
              >
                {t('purchaseInvoice.actions.pdf')}
              </a>
            )}
            {writer && invoice && isDraft && (
              <Button variant="secondary" onClick={() => setDialog('delete')}>
                {t('purchaseInvoice.actions.delete')}
              </Button>
            )}
            {writer && invoice && !isDraft && (
              <Button
                variant="secondary"
                busy={busy === 'duplicate'}
                onClick={onDuplicate}
              >
                {t('purchaseInvoice.actions.duplicate')}
              </Button>
            )}
            {writer && invoice && canVoid(invoice) && (
              <Button variant="secondary" onClick={() => setDialog('void')}>
                {t('purchaseInvoice.actions.void')}
              </Button>
            )}
            {writer && isDraft && (
              <>
                <Button
                  variant="secondary"
                  busy={busy === 'save'}
                  disabled={busy !== null}
                  onClick={onSave}
                >
                  {t('purchaseInvoice.actions.save')}
                </Button>
                <Button
                  busy={busy === 'emit'}
                  disabled={busy !== null}
                  onClick={onEmit}
                >
                  {t('purchaseInvoice.actions.emit')}
                </Button>
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
      {invoice && !isDraft && (
        <Alert kind={invoice.status === 'voided' ? 'warning' : 'info'}>
          {invoice.status === 'voided'
            ? t('purchaseInvoice.editor.voidedBecause', {
                reason: invoice.void_reason ?? '',
              })
            : `${t('purchaseInvoice.editor.readOnly')} ${t(
                'purchaseInvoice.editor.paid',
                {
                  paid: formatMoney(invoice.paid_amount),
                  total: formatMoney(invoice.net_total),
                  balance: formatMoney(invoice.balance),
                },
              )}`}
        </Alert>
      )}
      <DocumentEditor
        kind="purchase_invoice"
        value={draft}
        onChange={setDraft}
        readOnly={readOnly}
        errors={errors}
        headerExtra={
          <>
            <Field
              label={t('purchaseInvoice.editor.supplierNumber')}
              hint={t('purchaseInvoice.editor.supplierNumberHint')}
              error={errors['supplier_invoice_number']}
            >
              <input
                value={header.supplierInvoiceNumber}
                maxLength={40}
                onChange={(e) =>
                  setHeader((h) => ({
                    ...h,
                    supplierInvoiceNumber: e.target.value,
                  }))
                }
              />
            </Field>
            <Field
              label={t('purchaseInvoice.editor.dueDate')}
              error={errors['due_date']}
              optional
            >
              <DateInput
                value={header.dueDate}
                onChange={(iso) => setHeader((h) => ({...h, dueDate: iso}))}
              />
            </Field>
          </>
        }
        footerExtra={
          invoice &&
          draft.attachments.length > 0 && (
            <section className="purchase-downloads">
              <h3 className="admin-field-label">
                {t('purchaseInvoice.editor.downloads')}
              </h3>
              <ul>
                {draft.attachments.map((file) => (
                  <li key={file.id}>
                    <a href={supplierFileUrl(invoice.id, file.id)} download>
                      {t('purchaseInvoice.actions.download', {
                        name: file.name,
                      })}
                    </a>
                  </li>
                ))}
              </ul>
            </section>
          )
        }
        onAttach={writer && isDraft ? onAttach : undefined}
        onRemoveAttachment={
          writer && isDraft && invoice ? onRemoveAttachment : undefined
        }
      />
      {dialog === 'void' && invoice && (
        <VoidPurchaseInvoiceModal
          invoice={invoice}
          onClose={() => setDialog(null)}
          onVoided={(voided) => {
            setDialog(null);
            show(voided);
            setNotice(
              t('purchaseInvoice.notices.voided', {
                number: voided.number ?? '',
              }),
            );
          }}
        />
      )}
      {dialog === 'delete' && invoice && (
        <DeleteDraftModal
          onClose={() => setDialog(null)}
          onConfirm={async () => {
            await deletePurchaseInvoice(invoice.id);
            navigate(BASE, {
              state: {notice: t('purchaseInvoice.notices.deleted')},
            });
          }}
        />
      )}
    </>
  );
}

function DeleteDraftModal({
  onClose,
  onConfirm,
}: {
  onClose: () => void;
  onConfirm: () => Promise<void>;
}) {
  const {t} = useTranslation();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <FormModal
      title={t('purchaseInvoice.delete.title')}
      onClose={onClose}
      busy={busy}
      error={error}
      submitLabel={t('purchaseInvoice.delete.confirm')}
      onSubmit={async () => {
        setBusy(true);
        try {
          await onConfirm();
        } catch (failure) {
          setError(purchaseErrorMessage(failure, t));
          setBusy(false);
        }
      }}
    >
      <p>{t('purchaseInvoice.delete.body')}</p>
    </FormModal>
  );
}
