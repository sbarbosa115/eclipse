import {
  useEffect,
  useId,
  useRef,
  type KeyboardEvent,
  type ReactNode,
} from 'react';
import {listProducts, type Product, type Tax} from '@/entities/product';
import {AccountPicker} from '@/features/pick-account';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {
  ActionButton,
  Actions,
  Button,
  DataTable,
  IconButton,
  MoneyInput,
  SearchCombobox,
  type ComboOption,
} from '@/shared/ui';
import {setLineMode} from '../model/draft';
import {offeredTaxes} from '../model/useEditorOptions';
import type {LineAmounts} from '../model/totals';
import type {DocumentKind, DraftLine, EditorErrors} from '../model/types';
import {CellError, invalidProps} from './CellError';

type Column =
  | 'item'
  | 'description'
  | 'quantity'
  | 'unit_price'
  | 'discount'
  | 'charge'
  | 'withholding';

async function findProducts(term: string): Promise<ComboOption<Product>[]> {
  const page = await listProducts({q: term, active: '1', per_page: 10});
  return page.items.map((product) => ({
    id: product.id,
    label: `${product.code} · ${product.name}`,
    detail: formatMoney(product.sale_price),
    value: product,
  }));
}

/** Where the focus goes once the lines change: a cell of a line, or the first cell of the line just added. */
type PendingFocus = {key: string; col: Column} | {added: true} | null;

/**
 * The lines of the document: one row per line, a control per cell, the line's Valor total and its actions (taxes,
 * up, down, remove). From the keyboard: Tab walks the cells, Enter goes down a line (on the last one it adds a line),
 * Alt + ↑/↓ moves the line.
 */
export function LinesGrid({
  kind,
  lines,
  amounts,
  errors,
  readOnly,
  chargeTaxes,
  withholdingTaxes,
  knownTaxes,
  onChange,
  onAdd,
  onRemove,
  onMove,
  onPickProduct,
  onCreateProduct,
  onOpenTaxes,
}: {
  kind: DocumentKind;
  lines: DraftLine[];
  amounts: LineAmounts[];
  errors: EditorErrors;
  readOnly: boolean;
  /** The taxes in force on the document's date: what may be newly chosen. */
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
  /** Every active tax, so a line keeps showing the one it already has. */
  knownTaxes: Tax[];
  onChange: (index: number, change: Partial<DraftLine>) => void;
  onAdd: () => void;
  onRemove: (index: number) => void;
  onMove: (from: number, to: number) => void;
  onPickProduct: (index: number, product: Product) => void;
  onCreateProduct: (index: number, text: string) => void;
  onOpenTaxes: (index: number) => void;
}) {
  const {t} = useTranslation();
  const baseId = useId();
  const grid = useRef<HTMLDivElement>(null);
  const pending = useRef<PendingFocus>(null);
  const purchase = kind === 'purchase_invoice';

  const focusCell = (key: string, col: Column) => {
    const cell = grid.current?.querySelector(
      `tr[data-line="${key}"] td[data-col="${col}"]`,
    );
    const control = cell?.querySelector<HTMLElement>(
      col === 'item' ? 'input' : 'input, select',
    );
    control?.focus();
  };

  useEffect(() => {
    const target = pending.current;
    if (!target) return;
    pending.current = null;
    if ('added' in target) {
      const last = lines[lines.length - 1];
      if (last) focusCell(last.key, 'item');
    } else {
      focusCell(target.key, target.col);
    }
  }, [lines]);

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.defaultPrevented) return;
    const target = event.target as HTMLElement;
    if (target.tagName === 'BUTTON') return;
    const cell = target.closest<HTMLElement>('td[data-col]');
    const row = target.closest<HTMLElement>('tr[data-line]');
    if (!cell || !row) return;
    const col = cell.dataset['col'] as Column;
    const key = row.dataset['line'] ?? '';
    const index = lines.findIndex((line) => line.key === key);
    if (index < 0) return;

    if (
      event.altKey &&
      (event.key === 'ArrowUp' || event.key === 'ArrowDown')
    ) {
      event.preventDefault();
      const to = event.key === 'ArrowUp' ? index - 1 : index + 1;
      if (to < 0 || to >= lines.length) return;
      pending.current = {key, col};
      onMove(index, to);
    } else if (event.key === 'Enter' && !event.altKey) {
      // Enter never submits a form the page may wrap around the editor.
      event.preventDefault();
      const next = lines[index + 1];
      if (next) {
        focusCell(next.key, col);
      } else {
        pending.current = {added: true};
        onAdd();
      }
    }
  };

  const columns = [
    t(
      purchase
        ? 'documentEditor.lines.columns.itemPurchase'
        : 'documentEditor.lines.columns.item',
    ),
    t('documentEditor.lines.columns.description'),
    t('documentEditor.lines.columns.quantity'),
    t('documentEditor.lines.columns.unitPrice'),
    t('documentEditor.lines.columns.discount'),
    t('documentEditor.lines.columns.chargeTax'),
    t('documentEditor.lines.columns.withholdingTax'),
    t('documentEditor.lines.columns.total'),
  ];
  const label = (column: string, index: number) =>
    t('documentEditor.lines.cellLabel', {column, n: index + 1});

  const taxSelect = (
    index: number,
    col: 'charge' | 'withholding',
    line: DraftLine,
    taxes: Tax[],
    column: string,
  ): ReactNode => {
    const field = col === 'charge' ? 'charge_tax_id' : 'withholding_tax_id';
    const errorId = `${baseId}-${line.key}-${col}-error`;
    const message = errors[`lines.${index}.${field}`];
    return (
      <td data-col={col}>
        <select
          aria-label={label(column, index)}
          value={line[field] ?? ''}
          onChange={(e) => onChange(index, {[field]: e.target.value || null})}
          {...invalidProps(errorId, message)}
        >
          <option value="">{t('documentEditor.lines.noTax')}</option>
          {offeredTaxes(taxes, knownTaxes, line[field]).map((tax) => (
            <option key={tax.id} value={tax.id}>
              {tax.name}
            </option>
          ))}
        </select>
        <CellError id={errorId} message={message} />
      </td>
    );
  };

  const textCell = (
    index: number,
    col: 'description' | 'quantity' | 'unit_price' | 'discount',
    line: DraftLine,
    column: string,
  ): ReactNode => {
    const errorId = `${baseId}-${line.key}-${col}-error`;
    const message = errors[`lines.${index}.${col}`];
    return (
      <td data-col={col}>
        {col === 'unit_price' ? (
          <MoneyInput
            aria-label={label(column, index)}
            className="doc-line-number"
            places={4}
            value={line.unit_price}
            onChange={(unitPrice) => onChange(index, {unit_price: unitPrice})}
            {...invalidProps(errorId, message)}
          />
        ) : (
          <input
            aria-label={label(column, index)}
            className={
              col === 'description' ? 'doc-line-text' : 'doc-line-number'
            }
            inputMode={col === 'description' ? undefined : 'decimal'}
            value={line[col]}
            onChange={(e) => onChange(index, {[col]: e.target.value})}
            {...invalidProps(errorId, message)}
          />
        )}
        <CellError id={errorId} message={message} />
      </td>
    );
  };

  const itemCell = (index: number, line: DraftLine): ReactNode => {
    const errorId = `${baseId}-${line.key}-item-error`;
    const message =
      errors[`lines.${index}.product`] ?? errors[`lines.${index}.account`];
    const name = label(columns[0] ?? '', index);
    const inputId = `${baseId}-${line.key}-item`;
    return (
      <td data-col="item" className="doc-line-item">
        {purchase && (
          <select
            className="doc-line-mode"
            aria-label={t('documentEditor.lines.mode', {n: index + 1})}
            value={line.account ? 'account' : 'product'}
            onChange={(e) =>
              onChange(
                index,
                setLineMode(
                  line,
                  e.target.value === 'account' ? 'account' : 'product',
                ),
              )
            }
          >
            <option value="product">
              {t('documentEditor.lines.modeProduct')}
            </option>
            <option value="account">
              {t('documentEditor.lines.modeAccount')}
            </option>
          </select>
        )}
        {line.account ? (
          <>
            <label className="visually-hidden" htmlFor={inputId}>
              {name}
            </label>
            <AccountPicker
              id={inputId}
              purchases
              value={line.account}
              onChange={(account) => onChange(index, {account})}
              {...invalidProps(errorId, message)}
            />
          </>
        ) : (
          <SearchCombobox<Product>
            aria-label={name}
            selectedLabel={line.product?.label ?? ''}
            minChars={1}
            search={findProducts}
            placeholder={t('documentEditor.lines.productPlaceholder')}
            onSelect={(option) => onPickProduct(index, option.value)}
            onClear={() => onChange(index, {product: null})}
            onCreate={(text) => onCreateProduct(index, text)}
            {...invalidProps(errorId, message)}
          />
        )}
        <CellError id={errorId} message={message} />
      </td>
    );
  };

  return (
    <div ref={grid} className="doc-lines" onKeyDown={onKeyDown}>
      <DataTable
        columns={columns}
        rows={lines}
        actions={!readOnly}
        renderRow={(line, index) => (
          <tr key={line.key} data-line={line.key}>
            {itemCell(index, line)}
            {textCell(index, 'description', line, columns[1] ?? '')}
            {textCell(index, 'quantity', line, columns[2] ?? '')}
            {textCell(index, 'unit_price', line, columns[3] ?? '')}
            {textCell(index, 'discount', line, columns[4] ?? '')}
            {taxSelect(index, 'charge', line, chargeTaxes, columns[5] ?? '')}
            {taxSelect(
              index,
              'withholding',
              line,
              withholdingTaxes,
              columns[6] ?? '',
            )}
            <td className="doc-amount nowrap">
              {formatMoney(amounts[index]?.lineTotal ?? '0')}
            </td>
            {!readOnly && (
              <Actions>
                <IconButton
                  icon="sliders"
                  action="edit"
                  label={t('documentEditor.lines.taxes', {n: index + 1})}
                  onClick={() => onOpenTaxes(index)}
                />
                <ActionButton
                  action="setup"
                  aria-label={t('documentEditor.lines.up', {n: index + 1})}
                  disabled={index === 0}
                  onClick={() => onMove(index, index - 1)}
                >
                  ↑
                </ActionButton>
                <ActionButton
                  action="setup"
                  aria-label={t('documentEditor.lines.down', {n: index + 1})}
                  disabled={index === lines.length - 1}
                  onClick={() => onMove(index, index + 1)}
                >
                  ↓
                </ActionButton>
                <IconButton
                  icon="trash"
                  label={t('documentEditor.lines.remove', {n: index + 1})}
                  onClick={() => onRemove(index)}
                />
              </Actions>
            )}
          </tr>
        )}
      />
      {errors['lines'] && <p className="field-error">{errors['lines']}</p>}
      {!readOnly && (
        <div className="doc-lines-footer">
          <Button
            variant="secondary"
            size="sm"
            onClick={() => {
              pending.current = {added: true};
              onAdd();
            }}
          >
            {t('documentEditor.lines.add')}
          </Button>
          <span className="small muted">
            {t('documentEditor.lines.keyboardHint')}
          </span>
        </div>
      )}
    </div>
  );
}
