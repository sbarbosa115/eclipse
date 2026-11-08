import {useEffect, useMemo, useState} from 'react';
import {Link, useLocation, useNavigate, useParams} from 'react-router-dom';
import {
  canConvert,
  canSend,
  canVoid,
  createPurchaseOrder,
  duplicatePurchaseOrder,
  emitPurchaseOrder,
  getPurchaseOrder,
  purchaseOrderErrorMessage,
  purchaseOrderPdfUrl,
  sendPurchaseOrder,
  updatePurchaseOrder,
  voidPurchaseOrder,
  type PurchaseOrder,
  type Violation,
} from '@/entities/purchase-order';
import {listTaxes, type Tax} from '@/entities/product';
import {useSession} from '@/entities/session';
import {ConvertPurchaseOrder} from '@/features/convert-purchase-order';
import {EmitDocument} from '@/features/emit-document';
import {VoidDocumentModal} from '@/features/void-document';
import {ApiError} from '@/shared/api';
import {useTranslation, type Translate} from '@/shared/i18n';
import {formatDate} from '@/shared/lib';
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
  editorErrorsFrom,
  emptyDraft,
  hasErrors,
  validateDraft,
  type DocumentDraft,
  type EditorErrors,
} from '@/widgets/document-editor';
import {canWritePurchaseOrders} from '../model/access';
import {
  draftFromOrder,
  emptyExtras,
  extrasFromOrder,
  requestFromDraft,
  validateExtras,
  type PurchaseOrderExtras,
} from '../model/draftMapping';
import {PurchaseOrderHeaderFields} from './PurchaseOrderExtras';

type Load =
  {status: 'loading'} | {status: 'failed'; message: string} | {status: 'ready'};

/** "Línea 2: Este producto está inactivo." for a violation of a line; the message alone otherwise. */
function describeViolation(t: Translate, {field, message}: Violation): string {
  const line = /^lines(?:\.|\[)(\d+)/.exec(field);
  return line
    ? t('purchaseOrder.convert.line', {
        line: String(Number(line[1]) + 1),
        message,
      })
    : message;
}

/**
 * One orden de compra (observations PRD §4.8): a new or draft one is edited in the shared document form (a factura de
 * compra's lines, no formas de pago, with its Entrega esperada) and saved, emitted or emitted and sent; an emitted one
 * is shown read-only with its PDF, duplicate, send, void and "Convertir en factura", which opens the new draft purchase
 * invoice. What the invoice refuses on conversion is listed here.
 */
export function PurchaseOrderEditor() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const {id: routeId} = useParams();
  const {session} = useSession();
  const writer = canWritePurchaseOrders(session);
  const typeLabel = t('purchaseOrder.typeLabel');

  const [id, setId] = useState<string | null>(routeId ?? null);
  const [order, setOrder] = useState<PurchaseOrder | null>(null);
  const [draft, setDraft] = useState<DocumentDraft>(() =>
    emptyDraft({typeLabel}),
  );
  const [extras, setExtras] = useState<PurchaseOrderExtras>(emptyExtras);
  const [load, setLoad] = useState<Load>(
    routeId ? {status: 'loading'} : {status: 'ready'},
  );
  const [taxes, setTaxes] = useState<Tax[]>([]);
  const [errors, setErrors] = useState<EditorErrors>({});
  const [notice, setNotice] = useState<string | null>(
    (location.state as {notice?: string} | null)?.notice ?? null,
  );
  const [failure, setFailure] = useState<string | null>(null);
  const [refused, setRefused] = useState<Violation[]>([]);
  const [busy, setBusy] = useState<string | null>(null);
  const [voiding, setVoiding] = useState(false);

  useEffect(() => {
    let cancelled = false;
    Promise.all([listTaxes('charge'), listTaxes('withholding')])
      .then(([charge, withholding]) => {
        if (!cancelled) setTaxes([...charge, ...withholding]);
      })
      .catch(() => {});
    if (routeId) {
      getPurchaseOrder(routeId)
        .then((found) => {
          if (cancelled) return;
          setOrder(found);
          setDraft(draftFromOrder(found, t('purchaseOrder.typeLabel')));
          setExtras(extrasFromOrder(found));
          setLoad({status: 'ready'});
        })
        .catch((error) => {
          if (!cancelled) {
            setLoad({
              status: 'failed',
              message:
                error instanceof ApiError && error.status === 404
                  ? purchaseOrderErrorMessage(error, t)
                  : t('purchaseOrder.editor.loadFailed'),
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
  const isDraft = order === null || order.status === 'draft';
  const editable = writer && isDraft;

  const check = (forEmission: boolean): boolean => {
    const found = {
      ...validateDraft('purchase_order', draft, {
        net,
        methods: [],
        forEmission,
        t,
      }),
      ...validateExtras(draft, extras, (key) =>
        t(`purchaseOrder.validation.${key}`),
      ),
    };
    setErrors(found);
    if (hasErrors(found)) {
      setFailure(t('purchaseOrder.errors.validation_failed'));
      return false;
    }
    return true;
  };

  const show = (saved: PurchaseOrder) => {
    setOrder(saved);
    setDraft(draftFromOrder(saved, typeLabel));
    setExtras(extrasFromOrder(saved));
    setErrors({});
    setRefused([]);
  };

  /** Saves the draft; rejects with the message to show (and puts the fields' problems on the form). */
  const save = async (): Promise<PurchaseOrder> => {
    const {body, lineIndexes} = requestFromDraft(draft, extras);
    try {
      const saved = id
        ? await updatePurchaseOrder(id, body)
        : await createPurchaseOrder(body);
      setId(saved.id);
      return saved;
    } catch (error) {
      const fields = editorErrorsFrom(error, lineIndexes);
      if (fields) setErrors(fields);
      throw new Error(purchaseOrderErrorMessage(error, t));
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
          state: {notice: t('purchaseOrder.notices.saved')},
        });
        return;
      }
      show(saved);
      setNotice(t('purchaseOrder.notices.saved'));
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
    let emitted: PurchaseOrder;
    try {
      emitted = await emitPurchaseOrder(saved.id, send);
    } catch (error) {
      const fields = editorErrorsFrom(
        error,
        requestFromDraft(draft, extras).lineIndexes,
      );
      if (fields) setErrors(fields);
      throw new Error(purchaseOrderErrorMessage(error, t));
    }
    const message = t(
      send
        ? 'purchaseOrder.notices.emittedAndSent'
        : 'purchaseOrder.notices.emitted',
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
    setRefused([]);
    setBusy(name);
    try {
      await work();
    } catch (error) {
      setFailure(purchaseOrderErrorMessage(error, t));
    } finally {
      setBusy(null);
    }
  };

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <>
        <PageHeader
          title={t('purchaseOrder.title')}
          actions={
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('purchaseOrder.actions.back')}
            </Link>
          }
        />
        <ErrorState message={load.message} />
      </>
    );
  }

  const title = order?.number
    ? t('purchaseOrder.editor.title', {number: order.number})
    : order
      ? t('purchaseOrder.editor.draftTitle')
      : t('purchaseOrder.editor.newTitle');
  const status = order?.status;

  return (
    <>
      <PageHeader
        title={title}
        subtitle={
          order ? t(`purchaseOrder.statuses.${order.status}`) : undefined
        }
        actions={
          <>
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('purchaseOrder.actions.back')}
            </Link>
            {order && (
              <a
                href={purchaseOrderPdfUrl(order.id)}
                target="_blank"
                rel="noreferrer"
                className={actionClass('open', '', 'md')}
              >
                {t('purchaseOrder.actions.downloadPdf')}
              </a>
            )}
            {editable && (
              <>
                <Button
                  variant="secondary"
                  busy={busy === 'save'}
                  onClick={onSave}
                >
                  {t('purchaseOrder.actions.save')}
                </Button>
                <EmitDocument
                  emitLabel={t('purchaseOrder.actions.emit')}
                  emitAndSendLabel={t('purchaseOrder.actions.emitAndSend')}
                  confirmTitle={t('purchaseOrder.emit.title')}
                  confirmBody={t('purchaseOrder.emit.body')}
                  sendNote={t('purchaseOrder.emit.sendNote')}
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
            {writer && order && !isDraft && (
              <>
                <ActionButton
                  action="setup"
                  size="md"
                  busy={busy === 'duplicate'}
                  onClick={() =>
                    act('duplicate', async () => {
                      const copy = await duplicatePurchaseOrder(order.id);
                      navigate(`../${copy.id}`, {
                        relative: 'path',
                        state: {notice: t('purchaseOrder.notices.duplicated')},
                      });
                    })
                  }
                >
                  {t('purchaseOrder.actions.duplicate')}
                </ActionButton>
                {canSend(order) && (
                  <ActionButton
                    action="confirm"
                    size="md"
                    busy={busy === 'send'}
                    onClick={() =>
                      act('send', async () => {
                        await sendPurchaseOrder(order.id);
                        setNotice(
                          t('purchaseOrder.notices.sent', {
                            number: order.number ?? '',
                          }),
                        );
                      })
                    }
                  >
                    {t('purchaseOrder.actions.send')}
                  </ActionButton>
                )}
                {canConvert(order) && (
                  <ConvertPurchaseOrder
                    orderId={order.id}
                    disabled={busy !== null}
                    onConverted={(converted, invoiceId) =>
                      navigate(`/facturas-compra/${invoiceId}`, {
                        state: {
                          notice: t('purchaseOrder.notices.converted', {
                            number: converted.number ?? '',
                          }),
                        },
                      })
                    }
                    onRefused={(violations) => {
                      setNotice(null);
                      setFailure(null);
                      setRefused(violations);
                    }}
                    onFailed={(message) => {
                      setNotice(null);
                      setRefused([]);
                      setFailure(message);
                    }}
                  />
                )}
                {canVoid(order) && (
                  <ActionButton
                    action="danger"
                    size="md"
                    onClick={() => setVoiding(true)}
                  >
                    {t('purchaseOrder.actions.void')}
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
      {refused.length > 0 && (
        <Alert kind="error" onDismiss={() => setRefused([])}>
          <strong>{t('purchaseOrder.convert.refusedTitle')}</strong>
          <p>{t('purchaseOrder.convert.refusedBody')}</p>
          <ul>
            {refused.map((violation) => (
              <li key={`${violation.field}-${violation.message}`}>
                {describeViolation(t, violation)}
              </li>
            ))}
          </ul>
        </Alert>
      )}
      {status === 'voided' && order && (
        <Alert kind="info">
          {t('purchaseOrder.editor.voidedInfo', {
            date: formatDate(order.voided_at),
            reason: order.void_reason ?? '',
          })}
        </Alert>
      )}
      {status === 'converted' && order && (
        <Alert kind="info">
          {t('purchaseOrder.editor.convertedInfo')}{' '}
          {order.converted_invoice_id && (
            <Link to={`/facturas-compra/${order.converted_invoice_id}`}>
              {t('purchaseOrder.actions.viewInvoice')}
            </Link>
          )}
        </Alert>
      )}
      {order && !isDraft && status !== 'voided' && status !== 'converted' && (
        <Alert kind="info">{t('purchaseOrder.editor.readOnly')}</Alert>
      )}
      <DocumentEditor
        kind="purchase_order"
        value={draft}
        onChange={setDraft}
        readOnly={!editable}
        errors={errors}
        headerExtra={
          <PurchaseOrderHeaderFields
            extras={extras}
            errors={errors}
            onChange={(next) => setExtras({...extras, ...next})}
          />
        }
      />
      {voiding && order && (
        <VoidDocumentModal
          title={t('purchaseOrder.void.title', {number: order.number ?? ''})}
          body={t('purchaseOrder.void.body')}
          reasonLabel={t('purchaseOrder.void.reason')}
          reasonRequired={t('purchaseOrder.void.reasonRequired')}
          confirmLabel={t('purchaseOrder.void.confirm')}
          onClose={() => setVoiding(false)}
          onVoid={async (reason) => {
            let voided: PurchaseOrder;
            try {
              voided = await voidPurchaseOrder(order.id, reason);
            } catch (error) {
              throw new Error(purchaseOrderErrorMessage(error, t));
            }
            setVoiding(false);
            show(voided);
            setNotice(
              t('purchaseOrder.notices.voided', {number: voided.number ?? ''}),
            );
          }}
        />
      )}
    </>
  );
}
