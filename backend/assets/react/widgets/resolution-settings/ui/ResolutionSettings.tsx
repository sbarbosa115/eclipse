import './resolution.css';
import {useCallback, useEffect, useState} from 'react';
import {useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {
  Alert,
  Button,
  Card,
  ErrorState,
  Field,
  Loading,
  Modal,
  TabIntro,
} from '@/shared/ui';
import {
  confirmManualInvoicing,
  createResolution,
  getResolution,
  listNumbering,
  updateNumbering,
  updateResolution,
  updateWarnings,
  type NumberingSeries,
  type ResolutionPayload,
  type ResolutionSettings as Settings,
} from '../api/resolutionApi';
import {NumberingCard} from './NumberingCard';
import {ResolutionForm} from './ResolutionForm';
import {ResolutionStatusBanner} from './ResolutionStatusBanner';

type Notice = {kind: 'success' | 'error'; text: string} | null;

/**
 * Configuración › Resolución: the DIAN invoicing resolution with its status and warning, the thresholds of the warning
 * and the internal numbering series (§4.1). The owner edits; the other roles read.
 */
export function ResolutionSettings() {
  const {t} = useTranslation();
  const {session} = useSession();
  const canEdit = session?.role === 'owner';
  const [settings, setSettings] = useState<Settings | null>(null);
  const [series, setSeries] = useState<NumberingSeries[]>([]);
  const [failed, setFailed] = useState(false);
  const [notice, setNotice] = useState<Notice>(null);
  const [confirming, setConfirming] = useState(false);

  const [reads, setReads] = useState(0);
  const reload = useCallback(() => setReads((n) => n + 1), []);
  useEffect(() => {
    let current = true;
    Promise.all([getResolution(), listNumbering()]).then(
      ([loaded, items]) => {
        if (!current) return;
        setSettings(loaded);
        setSeries(items);
        setFailed(false);
      },
      () => current && setFailed(true),
    );
    return () => {
      current = false;
    };
  }, [reads]);

  if (failed)
    return <ErrorState message={t('common.loadFailed')} onRetry={reload} />;
  if (settings === null) return <Loading />;

  const saveResolution = async (
    payload: ResolutionPayload,
    creating: boolean,
  ) => {
    setSettings(
      await (creating ? createResolution(payload) : updateResolution(payload)),
    );
    setNotice({
      kind: 'success',
      text: t(
        creating
          ? 'company.resolution.notice.created'
          : 'company.resolution.notice.saved',
      ),
    });
  };

  const saveNumbering = async (
    kind: string,
    prefix: string,
    nextNumber: number,
  ) => {
    const saved = await updateNumbering(kind, prefix, nextNumber);
    setSeries((all) => all.map((s) => (s.kind === kind ? saved : s)));
    setNotice({kind: 'success', text: t('company.numbering.saved')});
  };

  const confirmManual = async () => {
    try {
      setSettings(await confirmManualInvoicing());
      setNotice({
        kind: 'success',
        text: t('company.resolution.notice.manualConfirmed'),
      });
    } catch (error) {
      setNotice({
        kind: 'error',
        text:
          error instanceof ApiError && error.code === 'forbidden'
            ? t('company.resolution.errors.forbidden')
            : t('common.errors.unexpected'),
      });
    } finally {
      setConfirming(false);
    }
  };

  return (
    <div className="resolution-tab">
      <TabIntro>
        {canEdit
          ? t('company.resolution.intro')
          : t('company.resolution.readOnly')}
      </TabIntro>
      <Alert kind={notice?.kind ?? 'info'} onDismiss={() => setNotice(null)}>
        {notice?.text}
      </Alert>
      <ResolutionStatusBanner settings={settings} />
      {/* Re-mounted after a save so the form shows what the server kept (the next number, a corrected prefix). */}
      <ResolutionForm
        key={JSON.stringify(settings.resolution)}
        settings={settings}
        canEdit={canEdit}
        onAskManualConfirmation={() => setConfirming(true)}
        onSave={saveResolution}
      />
      <WarningsCard
        key={`${settings.status.warning_numbers}-${settings.status.warning_days}`}
        numbers={settings.status.warning_numbers}
        days={settings.status.warning_days}
        canEdit={canEdit}
        onSave={async (numbers, days) => {
          setSettings(await updateWarnings(numbers, days));
          setNotice({
            kind: 'success',
            text: t('company.resolution.notice.warningsSaved'),
          });
        }}
      />
      <NumberingCard series={series} canEdit={canEdit} onSave={saveNumbering} />
      {confirming && (
        <Modal
          title={t('company.resolution.manual.title')}
          onClose={() => setConfirming(false)}
        >
          <p>{t('company.resolution.manual.body')}</p>
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setConfirming(false)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={() => void confirmManual()}>
              {t('company.resolution.manual.confirm')}
            </Button>
          </div>
        </Modal>
      )}
    </div>
  );
}

function WarningsCard({
  numbers,
  days,
  canEdit,
  onSave,
}: {
  numbers: number;
  days: number;
  canEdit: boolean;
  onSave: (numbers: number, days: number) => Promise<void>;
}) {
  const {t} = useTranslation();
  const [n, setN] = useState(String(numbers));
  const [d, setD] = useState(String(days));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setError(null);
    if (!/^\d+$/.test(n.trim()) || !/^\d+$/.test(d.trim())) {
      setError(t('company.errors.required'));
      return;
    }
    setBusy(true);
    try {
      await onSave(Number(n), Number(d));
    } catch (e) {
      setError(
        e instanceof ApiError && e.code === 'forbidden'
          ? t('company.resolution.errors.forbidden')
          : t('common.errors.unexpected'),
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card title={t('company.resolution.sections.warnings')}>
      <p className="muted small">{t('company.resolution.warnings.intro')}</p>
      <Alert kind="error">{error}</Alert>
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <fieldset disabled={!canEdit || busy} className="resolution-fieldset">
          <div className="form-grid">
            <Field label={t('company.resolution.warnings.numbers')}>
              <input
                inputMode="numeric"
                value={n}
                onChange={(e) => setN(e.target.value)}
              />
            </Field>
            <Field label={t('company.resolution.warnings.days')}>
              <input
                inputMode="numeric"
                value={d}
                onChange={(e) => setD(e.target.value)}
              />
            </Field>
          </div>
        </fieldset>
        {canEdit && (
          <div className="form-actions">
            <Button type="submit" busy={busy}>
              {t('company.resolution.warnings.save')}
            </Button>
          </div>
        )}
      </form>
    </Card>
  );
}
