import {useTranslation} from '@/shared/i18n';
import {DateInput, Field} from '@/shared/ui';
import type {EditorErrors} from '@/widgets/document-editor';
import type {PurchaseOrderExtras} from '../model/draftMapping';

/** headerExtra of the document form: the order's Entrega esperada (observations PRD §4.8). */
export function PurchaseOrderHeaderFields({
  extras,
  errors,
  onChange,
}: {
  extras: PurchaseOrderExtras;
  errors: EditorErrors;
  onChange: (next: Partial<PurchaseOrderExtras>) => void;
}) {
  const {t} = useTranslation();
  return (
    <Field
      label={t('purchaseOrder.fields.expected')}
      error={errors['expected_date']}
      optional
    >
      <DateInput
        value={extras.expected_date}
        onChange={(iso) => onChange({expected_date: iso})}
      />
    </Field>
  );
}
