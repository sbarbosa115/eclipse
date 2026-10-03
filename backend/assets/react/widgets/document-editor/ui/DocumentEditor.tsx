import {useMemo, useState, type ReactNode} from 'react';
import {QuickCreateProduct} from '@/features/quick-create-product';
import {LineTaxesDialog} from '@/features/line-taxes';
import {useTranslation} from '@/shared/i18n';
import {Alert} from '@/shared/ui';
import {
  addLine,
  addPayment,
  applyProduct,
  moveLine,
  removeLine,
  removePayment,
  setIssueDate,
  setTercero,
  updateLine,
  updatePayment,
} from '../model/draft';
import {computeTotals} from '../model/totals';
import type {DocumentDraft, DocumentKind, EditorErrors} from '../model/types';
import {useEditorOptions} from '../model/useEditorOptions';
import {FooterSection} from './FooterSection';
import {HeaderSection} from './HeaderSection';
import {LinesGrid} from './LinesGrid';
import {PaymentsSection} from './PaymentsSection';
import {TotalsPanel} from './TotalsPanel';
import './documentEditor.css';

export interface DocumentEditorProps {
  kind: DocumentKind;
  value: DocumentDraft;
  onChange: (next: DocumentDraft) => void;
  /** An emitted document: everything shown, nothing editable. */
  readOnly?: boolean;
  /** Messages by field path (see EditorErrors), from validateDraft() or the API's violations. */
  errors?: EditorErrors;
  /** The document's own header fields (Vendedor, Número del proveedor, Vencimiento de la oferta…). */
  headerExtra?: ReactNode;
  /** The document's own footer fields (Condiciones comerciales…). */
  footerExtra?: ReactNode;
  /** Files the person picked: the page uploads them and adds them to value.attachments. */
  onAttach?: (files: File[]) => void;
  onRemoveAttachment?: (id: string) => void;
}

/**
 * The form a cotización, factura de venta and factura de compra share (§4.6): header, lines, totals, formas de pago
 * (not on a quotation) and footer. Controlled: the page owns the draft and saves it; the totals shown are a preview
 * of the server's. Do not put it inside a <form>: its quick-create dialogs are forms of their own.
 */
export function DocumentEditor({
  kind,
  value,
  onChange,
  readOnly = false,
  errors = {},
  headerExtra,
  footerExtra,
  onAttach,
  onRemoveAttachment,
}: DocumentEditorProps) {
  const {t} = useTranslation();
  const options = useEditorOptions(kind);
  const [creatingFor, setCreatingFor] = useState<{
    key: string;
    text: string;
  } | null>(null);
  const [taxesFor, setTaxesFor] = useState<string | null>(null);

  const totals = useMemo(
    () =>
      computeTotals(value.lines, [
        ...options.chargeTaxes,
        ...options.withholdingTaxes,
      ]),
    [value.lines, options.chargeTaxes, options.withholdingTaxes],
  );

  const indexOf = (key: string) => value.lines.findIndex((l) => l.key === key);
  const taxLine = taxesFor === null ? -1 : indexOf(taxesFor);
  const taxLineValue = value.lines[taxLine];

  return (
    <fieldset className="doc-editor" disabled={readOnly}>
      {options.status === 'failed' && (
        <Alert kind="warning">{t('documentEditor.loadFailed')}</Alert>
      )}
      <HeaderSection
        kind={kind}
        value={value}
        errors={errors}
        onTercero={(tercero) => onChange(setTercero(value, tercero))}
        onContact={(contactId) => onChange({...value, contact_id: contactId})}
        onIssueDate={(date) => onChange(setIssueDate(value, date))}
        extra={headerExtra}
      />
      <section
        className="doc-section"
        aria-label={t('documentEditor.lines.title')}
      >
        <LinesGrid
          kind={kind}
          lines={value.lines}
          amounts={totals.lines}
          errors={errors}
          readOnly={readOnly}
          chargeTaxes={options.chargeTaxes}
          withholdingTaxes={options.withholdingTaxes}
          onChange={(index, change) =>
            onChange(updateLine(value, index, change))
          }
          onAdd={() => onChange(addLine(value))}
          onRemove={(index) => onChange(removeLine(value, index))}
          onMove={(from, to) => onChange(moveLine(value, from, to))}
          onPickProduct={(index, product) =>
            onChange(
              updateLine(
                value,
                index,
                applyProduct(
                  kind,
                  value.lines[index] ?? value.lines[0]!,
                  product,
                ),
              ),
            )
          }
          onCreateProduct={(index, text) =>
            setCreatingFor({key: value.lines[index]?.key ?? '', text})
          }
          onOpenTaxes={(index) => setTaxesFor(value.lines[index]?.key ?? null)}
        />
      </section>
      <div className="doc-bottom">
        <div className="doc-bottom-main">
          {kind !== 'quotation' && (
            <PaymentsSection
              payments={value.payments}
              methods={options.methods}
              net={totals.net}
              errors={errors}
              readOnly={readOnly}
              onAdd={() => onChange(addPayment(value, totals.net))}
              onUpdate={(index, change) =>
                onChange(updatePayment(value, index, change, options.methods))
              }
              onRemove={(index) => onChange(removePayment(value, index))}
            />
          )}
          <FooterSection
            notes={value.notes}
            attachments={value.attachments}
            errors={errors}
            readOnly={readOnly}
            onNotes={(notes) => onChange({...value, notes})}
            onAttach={onAttach}
            onRemoveAttachment={onRemoveAttachment}
            extra={footerExtra}
          />
        </div>
        <TotalsPanel totals={totals} />
      </div>
      {creatingFor && (
        <QuickCreateProduct
          initialName={creatingFor.text}
          onClose={() => setCreatingFor(null)}
          onCreated={(product) => {
            const index = indexOf(creatingFor.key);
            setCreatingFor(null);
            const line = value.lines[index];
            if (line) {
              onChange(
                updateLine(value, index, applyProduct(kind, line, product)),
              );
            }
          }}
        />
      )}
      {taxLineValue && (
        <LineTaxesDialog
          lineNumber={taxLine + 1}
          subtotal={totals.lines[taxLine]?.subtotal ?? '0.00'}
          chargeTaxId={taxLineValue.charge_tax_id}
          withholdingTaxId={taxLineValue.withholding_tax_id}
          chargeTaxes={options.chargeTaxes}
          withholdingTaxes={options.withholdingTaxes}
          productId={taxLineValue.product?.id ?? null}
          onClose={() => setTaxesFor(null)}
          onApply={(taxes) => {
            setTaxesFor(null);
            onChange(updateLine(value, taxLine, taxes));
          }}
        />
      )}
    </fieldset>
  );
}
