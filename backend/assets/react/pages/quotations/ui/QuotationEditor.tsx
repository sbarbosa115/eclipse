import {useEffect, useMemo, useState} from 'react';
import {Link, useLocation, useNavigate, useParams} from 'react-router-dom';
import {
  acceptQuotation,
  canConvert,
  canDecide,
  canSend,
  canVoid,
  createQuotation,
  duplicateQuotation,
  emitQuotation,
  getQuotation,
  quotationErrorMessage,
  quotationPdfUrl,
  rejectQuotation,
  sendQuotation,
  updateQuotation,
  voidQuotation,
  type Quotation,
  type Violation,
} from '@/entities/quotation';
import {listTaxes, type Tax} from '@/entities/product';
import {useSession} from '@/entities/session';
import {ConvertQuotation} from '@/features/convert-quotation';
import {EmitDocument} from '@/features/emit-document';
import {VoidDocumentModal} from '@/features/void-document';
import {ApiError} from '@/shared/api';
import {useTranslation, type Translate} from '@/shared/i18n';
import {addDays, formatDate} from '@/shared/lib';
import {
  actionClass,
  ActionButton,
  Alert,
  Button,
  ErrorState,
  Loading,
  Modal,
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
import {
  draftFromQuotation,
  emptyExtras,
  extrasFromQuotation,
  requestFromDraft,
  validateExtras,
  VALIDITY_DAYS,
  type QuotationExtras,
} from '../model/draftMapping';
import {canWriteQuotations} from '../model/access';
import {QuotationFooterFields, QuotationHeaderFields} from './QuotationExtras';

type Load =
  {status: 'loading'} | {status: 'failed'; message: string} | {status: 'ready'};

/** "Línea 2: Este producto está inactivo." for a violation of a line; the message alone otherwise. */
function describeViolation(t: Translate, {field, message}: Violation): string {
  const line = /^lines(?:\.|\[)(\d+)/.exec(field);
  return line
    ? t('quotation.convert.line', {line: String(Number(line[1]) + 1), message})
    : message;
}

/**
 * One cotización (§4.6, §4.7): a new or draft one is edited in the shared document form (with its responsable,
 * vencimiento, encabezado and condiciones comerciales) and saved, emitted or emitted and sent; an emitted one is shown
 * read-only with its PDF, duplicate, send, accept, reject, void and "Convertir a factura", which opens the new draft
 * invoice. What the invoice refuses on conversion is listed here.
 */
export function QuotationEditor() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const {id: routeId} = useParams();
  const {session} = useSession();
  const writer = canWriteQuotations(session);

  const [id, setId] = useState<string | null>(routeId ?? null);
  const [quotation, setQuotation] = useState<Quotation | null>(null);
  const [draft, setDraft] = useState<DocumentDraft>(() => {
    const blank = emptyDraft({typeLabel: t('quotation.typeLabel')});
    return blank;
  });
  const [extras, setExtras] = useState<QuotationExtras>(() =>
    emptyExtras(emptyDraft({typeLabel: ''}).issue_date),
  );
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
  const [deciding, setDeciding] = useState<'accept' | 'reject' | null>(null);

  const typeLabel = t('quotation.typeLabel');

  useEffect(() => {
    let cancelled = false;
    Promise.all([listTaxes('charge'), listTaxes('withholding')])
      .then(([charge, withholding]) => {
        if (!cancelled) setTaxes([...charge, ...withholding]);
      })
      .catch(() => {});
    if (routeId) {
      getQuotation(routeId)
        .then((found) => {
          if (cancelled) return;
          setQuotation(found);
          setDraft(draftFromQuotation(found, t('quotation.typeLabel')));
          setExtras(extrasFromQuotation(found));
          setLoad({status: 'ready'});
        })
        .catch((error) => {
          if (!cancelled) {
            setLoad({
              status: 'failed',
              message:
                error instanceof ApiError && error.status === 404
                  ? quotationErrorMessage(error, t)
                  : t('quotation.editor.loadFailed'),
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
  const isDraft = quotation === null || quotation.status === 'draft';
  const editable = writer && isDraft;

  const check = (forEmission: boolean): boolean => {
    const found = {
      ...validateDraft('quotation', draft, {net, methods: [], forEmission, t}),
      ...validateExtras(draft, extras, (key) =>
        t(`quotation.validation.${key}`),
      ),
    };
    setErrors(found);
    if (hasErrors(found)) {
      setFailure(t('quotation.errors.validation_failed'));
      return false;
    }
    return true;
  };

  const show = (saved: Quotation) => {
    setQuotation(saved);
    setDraft(draftFromQuotation(saved, typeLabel));
    setExtras(extrasFromQuotation(saved));
    setErrors({});
    setRefused([]);
  };

  const changeDraft = (next: DocumentDraft) => {
    // A new date of elaboración moves the offer's vencimiento along until the person sets it by hand (§9 Q17).
    if (
      !extras.expiry_touched &&
      next.issue_date !== draft.issue_date &&
      /^\d{4}-\d{2}-\d{2}$/.test(next.issue_date)
    ) {
      setExtras({
        ...extras,
        expiry_date: addDays(next.issue_date, VALIDITY_DAYS),
      });
    }
    setDraft(next);
  };

  /** Saves the draft; rejects with the message to show (and puts the fields' problems on the form). */
  const save = async (): Promise<Quotation> => {
    const {body, lineIndexes} = requestFromDraft(draft, extras);
    try {
      const saved = id
        ? await updateQuotation(id, body)
        : await createQuotation(body);
      setId(saved.id);
      return saved;
    } catch (error) {
      const fields = editorErrorsFrom(error, lineIndexes);
      if (fields) setErrors(fields);
      throw new Error(quotationErrorMessage(error, t));
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
          state: {notice: t('quotation.notices.saved')},
        });
        return;
      }
      show(saved);
      setNotice(t('quotation.notices.saved'));
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
    let emitted: Quotation;
    try {
      emitted = await emitQuotation(saved.id, send);
    } catch (error) {
      const fields = editorErrorsFrom(
        error,
        requestFromDraft(draft, extras).lineIndexes,
      );
      if (fields) setErrors(fields);
      throw new Error(quotationErrorMessage(error, t));
    }
    const message = t(
      send ? 'quotation.notices.emittedAndSent' : 'quotation.notices.emitted',
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
      setFailure(quotationErrorMessage(error, t));
    } finally {
      setBusy(null);
    }
  };

  if (load.status === 'loading') return <Loading />;
  if (load.status === 'failed') {
    return (
      <>
        <PageHeader
          title={t('quotation.title')}
          actions={
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('quotation.actions.back')}
            </Link>
          }
        />
        <ErrorState message={load.message} />
      </>
    );
  }

  const title = quotation?.number
    ? t('quotation.editor.title', {number: quotation.number})
    : quotation
      ? t('quotation.editor.draftTitle')
      : t('quotation.editor.newTitle');
  const status = quotation?.status;

  return (
    <>
      <PageHeader
        title={title}
        subtitle={
          quotation ? t(`quotation.statuses.${quotation.status}`) : undefined
        }
        actions={
          <>
            <Link to=".." relative="path" className="btn btn-ghost">
              {t('quotation.actions.back')}
            </Link>
            {quotation && (
              <a
                href={quotationPdfUrl(quotation.id)}
                target="_blank"
                rel="noreferrer"
                className={actionClass('open', '', 'md')}
              >
                {t('quotation.actions.downloadPdf')}
              </a>
            )}
            {editable && (
              <>
                <Button
                  variant="secondary"
                  busy={busy === 'save'}
                  onClick={onSave}
                >
                  {t('quotation.actions.save')}
                </Button>
                <EmitDocument
                  emitLabel={t('quotation.actions.emit')}
                  emitAndSendLabel={t('quotation.actions.emitAndSend')}
                  confirmTitle={t('quotation.emit.title')}
                  confirmBody={t('quotation.emit.body')}
                  sendNote={t('quotation.emit.sendNote')}
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
            {writer && quotation && !isDraft && (
              <>
                <ActionButton
                  action="setup"
                  size="md"
                  busy={busy === 'duplicate'}
                  onClick={() =>
                    act('duplicate', async () => {
                      const copy = await duplicateQuotation(quotation.id);
                      navigate(`../${copy.id}`, {
                        relative: 'path',
                        state: {notice: t('quotation.notices.duplicated')},
                      });
                    })
                  }
                >
                  {t('quotation.actions.duplicate')}
                </ActionButton>
                {canSend(quotation) && (
                  <ActionButton
                    action="confirm"
                    size="md"
                    busy={busy === 'send'}
                    onClick={() =>
                      act('send', async () => {
                        await sendQuotation(quotation.id);
                        setNotice(
                          t('quotation.notices.sent', {
                            number: quotation.number ?? '',
                          }),
                        );
                      })
                    }
                  >
                    {t('quotation.actions.send')}
                  </ActionButton>
                )}
                {canDecide(quotation) && (
                  <>
                    <ActionButton
                      action="confirm"
                      size="md"
                      onClick={() => setDeciding('accept')}
                    >
                      {t('quotation.actions.accept')}
                    </ActionButton>
                    <ActionButton
                      action="setup"
                      size="md"
                      onClick={() => setDeciding('reject')}
                    >
                      {t('quotation.actions.reject')}
                    </ActionButton>
                  </>
                )}
                {canConvert(quotation) && (
                  <ConvertQuotation
                    quotationId={quotation.id}
                    disabled={busy !== null}
                    onConverted={(converted, invoiceId) =>
                      navigate(`/facturas-venta/${invoiceId}`, {
                        state: {
                          notice: t('quotation.notices.converted', {
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
                {canVoid(quotation) && (
                  <ActionButton
                    action="danger"
                    size="md"
                    onClick={() => setVoiding(true)}
                  >
                    {t('quotation.actions.void')}
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
          <strong>{t('quotation.convert.refusedTitle')}</strong>
          <p>{t('quotation.convert.refusedBody')}</p>
          <ul>
            {refused.map((violation) => (
              <li key={`${violation.field}-${violation.message}`}>
                {describeViolation(t, violation)}
              </li>
            ))}
          </ul>
        </Alert>
      )}
      {status === 'voided' && quotation && (
        <Alert kind="info">
          {t('quotation.editor.voidedInfo', {
            date: formatDate(quotation.voided_at),
            reason: quotation.void_reason ?? '',
          })}
        </Alert>
      )}
      {status === 'expired' && quotation && (
        <Alert kind="info">
          {t('quotation.editor.expiredInfo', {
            date: formatDate(quotation.expiry_date),
          })}
        </Alert>
      )}
      {status === 'rejected' && (
        <Alert kind="info">{t('quotation.editor.rejectedInfo')}</Alert>
      )}
      {status === 'accepted' && quotation && (
        <Alert kind="info">
          {t('quotation.editor.acceptedInfo')}{' '}
          {quotation.converted_invoice_id && (
            <>
              {t('quotation.convert.converted')}{' '}
              <Link to={`/facturas-venta/${quotation.converted_invoice_id}`}>
                {t('quotation.actions.viewInvoice')}
              </Link>
            </>
          )}
        </Alert>
      )}
      {quotation && !isDraft && status !== 'voided' && (
        <Alert kind="info">{t('quotation.editor.readOnly')}</Alert>
      )}
      <DocumentEditor
        kind="quotation"
        value={draft}
        onChange={changeDraft}
        readOnly={!editable}
        errors={errors}
        headerExtra={
          <QuotationHeaderFields
            extras={extras}
            errors={errors}
            onChange={(next) => setExtras({...extras, ...next})}
          />
        }
        footerExtra={
          <QuotationFooterFields
            extras={extras}
            errors={errors}
            onChange={(next) => setExtras({...extras, ...next})}
          />
        }
      />
      {voiding && quotation && (
        <VoidDocumentModal
          title={t('quotation.void.title', {number: quotation.number ?? ''})}
          body={t('quotation.void.body')}
          reasonLabel={t('quotation.void.reason')}
          reasonRequired={t('quotation.void.reasonRequired')}
          confirmLabel={t('quotation.void.confirm')}
          onClose={() => setVoiding(false)}
          onVoid={async (reason) => {
            let voided: Quotation;
            try {
              voided = await voidQuotation(quotation.id, reason);
            } catch (error) {
              throw new Error(quotationErrorMessage(error, t));
            }
            setVoiding(false);
            show(voided);
            setNotice(
              t('quotation.notices.voided', {number: voided.number ?? ''}),
            );
          }}
        />
      )}
      {deciding && quotation && (
        <Modal
          title={t(
            deciding === 'accept'
              ? 'quotation.decide.acceptTitle'
              : 'quotation.decide.rejectTitle',
          )}
          onClose={() => busy === null && setDeciding(null)}
        >
          <p>
            {t(
              deciding === 'accept'
                ? 'quotation.decide.acceptBody'
                : 'quotation.decide.rejectBody',
            )}
          </p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setDeciding(null)}>
              {t('common.cancel')}
            </Button>
            <Button
              busy={busy === deciding}
              onClick={() => {
                const action = deciding;
                void act(action, async () => {
                  const answered =
                    action === 'accept'
                      ? await acceptQuotation(quotation.id)
                      : await rejectQuotation(quotation.id);
                  setDeciding(null);
                  show(answered);
                  setNotice(
                    t(
                      action === 'accept'
                        ? 'quotation.notices.accepted'
                        : 'quotation.notices.rejected',
                      {number: answered.number ?? ''},
                    ),
                  );
                }).finally(() => setDeciding(null));
              }}
            >
              {t(
                deciding === 'accept'
                  ? 'quotation.actions.accept'
                  : 'quotation.actions.reject',
              )}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
