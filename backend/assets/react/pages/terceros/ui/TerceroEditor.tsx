import {useEffect, useState} from 'react';
import {Link, useNavigate, useParams} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {
  createTercero,
  describeErrors,
  emptyForm,
  eraseTercero,
  exportTercero,
  fetchTercero,
  formFromTercero,
  toPayload,
  updateTercero,
  validateForm,
  violationsOf,
  terceroErrorMessage,
  type Tercero,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {formatDate} from '@/shared/lib';
import {Alert, ErrorState, Loading, PageHeader} from '@/shared/ui';
import {TerceroFormView} from './TerceroFormView';
import {canWriteTerceros} from './TercerosList';
import {downloadJson} from './downloadJson';
import './terceros.css';

type Loaded =
  {id: string; status: 'ok'; tercero: Tercero} | {id: string; status: 'failed'};

/** The full tercero form as a page: /terceros/nuevo creates, /terceros/:id edits (or shows, for the accountant). */
export function TerceroEditor() {
  const {t} = useTranslation();
  const {id} = useParams();
  const {session} = useSession();
  const [loaded, setLoaded] = useState<Loaded | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    fetchTercero(id)
      .then((tercero) => !cancelled && setLoaded({id, status: 'ok', tercero}))
      .catch(() => !cancelled && setLoaded({id, status: 'failed'}));
    return () => {
      cancelled = true;
    };
  }, [id, attempt]);

  const writer = canWriteTerceros(session?.role);
  const back = (
    <Link to="/terceros" className="btn btn-ghost">
      {t('terceros.form.back')}
    </Link>
  );

  if (!id) {
    return (
      <>
        <PageHeader title={t('terceros.form.newTitle')} actions={back} />
        <TerceroEditorBody tercero={null} writer={writer} />
      </>
    );
  }
  if (loaded?.id !== id) return <Loading />;
  if (loaded.status === 'failed') {
    return (
      <ErrorState
        message={t('terceros.form.loadFailed')}
        onRetry={() => setAttempt((n) => n + 1)}
      />
    );
  }
  const {tercero} = loaded;
  return (
    <>
      <PageHeader
        title={
          tercero.erased_at
            ? t('terceros.form.viewTitle')
            : writer
              ? t('terceros.form.editTitle')
              : t('terceros.form.viewTitle')
        }
        subtitle={tercero.display_name}
        actions={back}
      />
      <TerceroEditorBody
        key={`${tercero.id}-${tercero.erased_at ?? ''}`}
        tercero={tercero}
        writer={writer}
        onChanged={(next) => setLoaded({id, status: 'ok', tercero: next})}
      />
    </>
  );
}

function TerceroEditorBody({
  tercero,
  writer,
  onChanged,
}: {
  tercero: Tercero | null;
  writer: boolean;
  onChanged?: (tercero: Tercero) => void;
}) {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const [form, setForm] = useState(() =>
    tercero ? formFromTercero(tercero) : emptyForm(),
  );
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  const erased = tercero?.erased_at != null;

  const submit = async () => {
    const found = validateForm(form);
    setErrors(describeErrors(found, t));
    setSaved(false);
    if (Object.keys(found).length > 0) {
      setFailure(t('terceros.form.saveFailed'));
      return;
    }
    setFailure(null);
    setBusy(true);
    try {
      if (tercero) {
        const next = await updateTercero(tercero.id, toPayload(form));
        onChanged?.(next);
        setForm(formFromTercero(next));
        setSaved(true);
      } else {
        const created = await createTercero(toPayload(form));
        navigate('/terceros', {
          replace: true,
          state: {
            notice: t('terceros.form.createdNotice', {
              name: created.display_name,
            }),
          },
        });
      }
    } catch (error) {
      const fields = violationsOf(error);
      setErrors(fields);
      setFailure(
        Object.keys(fields).length > 0
          ? t('terceros.form.saveFailed')
          : terceroErrorMessage(error, t),
      );
    } finally {
      setBusy(false);
    }
  };

  const exportData = async () => {
    if (!tercero) return;
    try {
      const data = await exportTercero(tercero.id);
      downloadJson(`tercero-${tercero.identification_number}.json`, data);
    } catch {
      throw new Error(t('terceros.privacy.exportFailed'));
    }
  };

  const erase = async () => {
    if (!tercero) return;
    try {
      onChanged?.(await eraseTercero(tercero.id));
    } catch (error) {
      throw new Error(terceroErrorMessage(error, t));
    }
  };

  return (
    <>
      {erased && tercero && (
        <Alert kind="warning">
          {t('terceros.form.erasedNotice', {
            date: formatDate(tercero.erased_at),
          })}
        </Alert>
      )}
      <TerceroFormView
        form={form}
        onChange={(patch) => {
          setForm((current) => ({...current, ...patch}));
          setSaved(false);
        }}
        errors={errors}
        failure={failure}
        saved={saved}
        busy={busy}
        readOnly={!writer || erased}
        tercero={tercero}
        onSubmit={submit}
        onExport={exportData}
        onErase={erase}
      />
    </>
  );
}
