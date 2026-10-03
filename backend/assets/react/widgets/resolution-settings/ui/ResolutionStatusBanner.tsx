import {useTranslation} from '@/shared/i18n';
import {formatDate} from '@/shared/lib';
import {Alert} from '@/shared/ui';
import type {ResolutionSettings} from '../api/resolutionApi';

/**
 * What the owner reads first: where the resolution stands, and a warning, in its own colour, when it is close to
 * running out (§4.1). A blocked one (expired, exhausted) is an error: emission is impossible.
 */
export function ResolutionStatusBanner({
  settings,
}: {
  settings: ResolutionSettings;
}) {
  const {t} = useTranslation();
  const {status, resolution} = settings;
  const params = {
    numbers: String(status.numbers_left),
    days: String(status.days_left),
    date: formatDate(resolution?.valid_from),
  };

  if (status.warning) {
    return (
      <Alert kind="warning">{t('company.resolution.warning', params)}</Alert>
    );
  }
  const kind =
    status.status === 'active'
      ? 'success'
      : status.status === 'expired' || status.status === 'exhausted'
        ? 'error'
        : 'info';
  return (
    <Alert kind={kind}>
      {t(`company.resolution.status.${status.status}`, params)}
    </Alert>
  );
}
