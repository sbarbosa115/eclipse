import {useMemo, useState} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Alert, Button, PageHeader} from '@/shared/ui';
import {emptyDraft, hasErrors, validateDraft} from '../model/draft';
import {computeTotals} from '../model/totals';
import type {
  Attachment,
  DocumentDraft,
  DocumentKind,
  EditorErrors,
} from '../model/types';
import {useEditorOptions} from '../model/useEditorOptions';
import {DocumentEditor} from './DocumentEditor';

const KINDS: DocumentKind[] = [
  'sales_invoice',
  'purchase_invoice',
  'quotation',
];

/**
 * Development only (routed under /dev/ when NODE_ENV is not production): the editor on a page of its own, so the
 * smoke suite and a person can try it against the real API before the document pages (items 8–10) mount it. Nothing
 * is saved.
 */
export function DocumentEditorDemo() {
  const {t} = useTranslation();
  const [kind, setKind] = useState<DocumentKind>('sales_invoice');
  const [draft, setDraft] = useState<DocumentDraft>(() =>
    emptyDraft({typeLabel: t('documentEditor.demo.types.sales_invoice')}),
  );
  const [errors, setErrors] = useState<EditorErrors | null>(null);
  const [readOnly, setReadOnly] = useState(false);
  const options = useEditorOptions(kind, draft.issue_date);
  const net = useMemo(
    () =>
      computeTotals(draft.lines, [
        ...options.chargeTaxes,
        ...options.withholdingTaxes,
      ]).net,
    [draft.lines, options.chargeTaxes, options.withholdingTaxes],
  );

  const switchTo = (next: DocumentKind) => {
    setKind(next);
    setErrors(null);
    setDraft(emptyDraft({typeLabel: t(`documentEditor.demo.types.${next}`)}));
  };

  return (
    <>
      <PageHeader
        title={t('documentEditor.demo.title')}
        subtitle={t('documentEditor.demo.subtitle')}
        actions={
          <>
            <label className="filter-select">
              <span className="filter-select-label">
                {t('documentEditor.demo.kind')}
              </span>
              <select
                value={kind}
                onChange={(e) => switchTo(e.target.value as DocumentKind)}
              >
                {KINDS.map((k) => (
                  <option key={k} value={k}>
                    {t(`documentEditor.demo.types.${k}`)}
                  </option>
                ))}
              </select>
            </label>
            <Button variant="secondary" onClick={() => setReadOnly((r) => !r)}>
              {t(
                readOnly
                  ? 'documentEditor.demo.edit'
                  : 'documentEditor.demo.readOnly',
              )}
            </Button>
            <Button
              onClick={() =>
                setErrors(
                  validateDraft(kind, draft, {
                    net,
                    methods: options.methods,
                    forEmission: true,
                    t,
                  }),
                )
              }
            >
              {t('documentEditor.demo.check')}
            </Button>
          </>
        }
      />
      {errors && !hasErrors(errors) && (
        <Alert kind="success">{t('documentEditor.demo.ready')}</Alert>
      )}
      <DocumentEditor
        kind={kind}
        value={draft}
        onChange={setDraft}
        readOnly={readOnly}
        errors={errors ?? {}}
        onAttach={(files) =>
          setDraft((d) => ({
            ...d,
            attachments: [
              ...d.attachments,
              ...files.map((file, i): Attachment => ({
                id: `${Date.now()}-${i}`,
                name: file.name,
                size: file.size,
              })),
            ],
          }))
        }
        onRemoveAttachment={(id) =>
          setDraft((d) => ({
            ...d,
            attachments: d.attachments.filter((a) => a.id !== id),
          }))
        }
      />
    </>
  );
}
