import {useTranslation} from '@/shared/i18n';
import {Alert} from '@/shared/ui';

/** The footnote of a period with voided invoices: they are left out, and a void in a later month explains a difference. */
export function VoidedNote({count}: {count: number}) {
  const {t} = useTranslation();
  if (count === 0) return null;
  return (
    <Alert kind="info">
      {count === 1
        ? t('taxReports.voided.one')
        : t('taxReports.voided.other', {count})}
    </Alert>
  );
}
