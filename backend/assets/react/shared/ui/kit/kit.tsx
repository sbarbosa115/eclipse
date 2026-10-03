// The app's component kit (the house style, from the Tacoma admin): every table, button, form field and modal comes
// from here, so screens cannot drift apart. Styles are admin.css, scoped under `.admin` (the app's root).
import {
  Children,
  cloneElement,
  isValidElement,
  useEffect,
  useId,
  useRef,
  useState,
  type ButtonHTMLAttributes,
  type HTMLAttributes,
  type ReactElement,
  type ReactNode,
} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Icon} from '../Icon';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  /** `link` only for text that is a link; never for an action (see ActionButton). */
  variant?: 'primary' | 'secondary' | 'ghost' | 'link';
  size?: 'sm';
  busy?: boolean;
};

export function Button({
  variant = 'primary',
  size,
  busy = false,
  className = '',
  children,
  ...props
}: ButtonProps) {
  const {t} = useTranslation();
  const classes = ['btn', `btn-${variant}`, size && `btn-${size}`, className]
    .filter(Boolean)
    .join(' ');
  return (
    <button
      type="button"
      className={classes}
      {...props}
      disabled={busy || props.disabled}
    >
      {busy ? t('common.working') : children}
    </button>
  );
}

/**
 * What each kind of action looks like, the same in every table and modal: the colour says what the button does.
 *
 * - confirm (green): complete, confirm, enable
 * - danger (red): remove, cancel, stop
 * - edit (blue): edit
 * - open (violet): open, view, go to a related page
 * - setup (indigo): add to something, act as someone
 */
export const ACTIONS = ['confirm', 'danger', 'edit', 'open', 'setup'] as const;
export type Action = (typeof ACTIONS)[number];

// The action an icon stands for, so an icon button is coloured without each page saying so.
const ICON_ACTIONS: Record<string, Action> = {
  check: 'confirm',
  trash: 'danger',
  close: 'danger',
  pencil: 'edit',
  eye: 'open',
  external: 'open',
  plus: 'setup',
};

/** The classes of an action button, for elements that are not a <button> (a router Link). */
export function actionClass(
  action: Action,
  className = '',
  size: 'sm' | 'md' = 'sm',
): string {
  return [
    'btn',
    size === 'sm' && 'btn-sm',
    'btn-action',
    `btn-action-${action}`,
    className,
  ]
    .filter(Boolean)
    .join(' ');
}

type ActionButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  action: Action;
  size?: 'sm' | 'md';
  busy?: boolean;
};

/** A worded button in an Actions cell: outlined in the colour of what it does, so it reads as a button. */
export function ActionButton({
  action,
  size = 'sm',
  busy = false,
  className = '',
  children,
  ...props
}: ActionButtonProps) {
  const {t} = useTranslation();
  return (
    <button
      type="button"
      className={actionClass(action, className, size)}
      {...props}
      disabled={busy || props.disabled}
    >
      {busy ? t('common.working') : children}
    </button>
  );
}

type IconButtonProps = Omit<
  ButtonHTMLAttributes<HTMLButtonElement>,
  'children'
> & {
  icon: string;
  /** The tooltip and the accessible name: never omitted. */
  label: string;
  action?: Action;
  busy?: boolean;
};

/** A compact button showing only an icon, for the actions an icon says on its own (view, edit, remove). */
export function IconButton({
  icon,
  label,
  action,
  busy = false,
  className = '',
  ...props
}: IconButtonProps) {
  const tone = action ?? ICON_ACTIONS[icon] ?? 'open';
  return (
    <button
      type="button"
      className={`btn btn-action btn-action-${tone} btn-icon${busy ? ' is-busy' : ''} ${className}`.trim()}
      aria-label={label}
      data-tooltip={label}
      aria-busy={busy || undefined}
      {...props}
      disabled={busy || props.disabled}
    >
      <Icon name={icon} size={16} />
    </button>
  );
}

interface FieldProps {
  label: ReactNode;
  error?: string | null;
  hint?: ReactNode;
  optional?: boolean;
  className?: string;
  /** One input, select or textarea: it gets the label's id, and the hint and error describe it. */
  children: ReactElement<{
    'id'?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
  }>;
}

/**
 * A labelled form control. The label names the control and nothing else; the hint or the error describe it
 * (aria-describedby), so a screen reader says "Name, edit text, This field is required."
 */
export function Field({
  label,
  error,
  hint,
  optional = false,
  className = '',
  children,
}: FieldProps) {
  const {t} = useTranslation();
  const id = useId();
  const note = error ? `${id}-error` : hint ? `${id}-hint` : undefined;
  return (
    <div
      className={`field${error ? ' field-invalid' : ''} ${className}`.trim()}
    >
      <label className="admin-field-label" htmlFor={id}>
        {label}
        {optional && (
          <span className="admin-field-optional">
            {' '}
            ({t('common.optional')})
          </span>
        )}
      </label>
      {cloneElement(children, {
        'id': id,
        'aria-describedby': note,
        'aria-invalid': error ? true : undefined,
      })}
      {hint && !error && (
        <span id={`${id}-hint`} className="admin-field-hint">
          {hint}
        </span>
      )}
      {error && (
        <span id={`${id}-error`} className="field-error">
          {error}
        </span>
      )}
    </div>
  );
}

export function Alert({
  kind = 'info',
  children,
  onDismiss,
}: {
  kind?: 'info' | 'success' | 'warning' | 'error';
  children?: ReactNode;
  onDismiss?: () => void;
}) {
  const {t} = useTranslation();
  if (!children) return null;
  return (
    <div
      className={`alert alert-${kind}`}
      role={kind === 'error' ? 'alert' : 'status'}
    >
      <span>{children}</span>
      {onDismiss && (
        <button
          type="button"
          className="icon-btn"
          onClick={onDismiss}
          aria-label={t('common.close')}
        >
          <Icon name="close" size={16} />
        </button>
      )}
    </div>
  );
}

/**
 * Status value → colour, shared by badges and row tints so the two never disagree. A value left out shows neutral,
 * which reads as "no status".
 */
const TONES: Record<string, string> = {
  active: 'success',
  prospect: 'warning',
  owner: 'info',
  super_admin: 'accent',
  // Appointments: a request waits for the owner, a confirmed one is booked, the others are over.
  requested: 'warning',
  confirmed: 'success',
  declined: 'neutral',
  cancelled: 'neutral',
  // Orders: one waits for its owner, then it is in the owner's hands, on its way, handed over, and over.
  order_received: 'warning',
  order_confirmed: 'info',
  order_preparing: 'info',
  order_in_transit: 'accent',
  order_ready_for_pickup: 'accent',
  order_delivered: 'success',
  order_completed: 'neutral',
  order_cancelled: 'neutral',
  // Contact messages: one waits for an answer, then the business answered, then there is nothing more to do.
  message_new: 'warning',
  message_contacted: 'info',
  message_closed: 'neutral',
  // The assistant's files: one is being read, then the assistant answers from it, or it could not be read.
  knowledge_reading: 'info',
  knowledge_ready: 'success',
  knowledge_failed: 'warning',
  // An import's records: new ones go in, replaced ones change, the others stay as they are.
  new: 'success',
  created: 'success',
  replace: 'info',
  replaced: 'info',
  exists: 'neutral',
  skipped: 'neutral',
  invalid: 'warning',
};

export function toneFor(status: string): string {
  return TONES[status] ?? 'neutral';
}

export function Badge({
  value,
  children,
}: {
  value: string;
  children?: ReactNode;
}) {
  return <span className={`badge badge-${toneFor(value)}`}>{children}</span>;
}

export function Loading() {
  const {t} = useTranslation();
  return (
    <div className="loading" role="status">
      <span className="spinner" aria-hidden="true" />
      {t('common.loading')}
    </div>
  );
}

export function FullPageLoading() {
  return (
    <div className="full-page-center">
      <Loading />
    </div>
  );
}

export function ErrorState({
  message,
  onRetry,
}: {
  message: ReactNode;
  onRetry?: () => void;
}) {
  const {t} = useTranslation();
  return (
    <div className="state state-error" role="alert">
      <p>{message}</p>
      {onRetry && (
        <Button variant="ghost" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      )}
    </div>
  );
}

export function EmptyState({
  children,
  action,
}: {
  children?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="state">
      <p>{children}</p>
      {action}
    </div>
  );
}

/** One line under a page's tab bar saying what the tab is for, with the tab's own primary action beside it. */
export function TabIntro({
  children,
  action,
}: {
  children?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="tab-intro">
      <p>{children}</p>
      {action}
    </div>
  );
}

export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="page-header">
      <div>
        <h1>{title}</h1>
        {subtitle && <p className="page-subtitle">{subtitle}</p>}
      </div>
      {actions && <div className="page-actions">{actions}</div>}
    </div>
  );
}

/** A search box that tells its owner 300 ms after the typing stops. */
export function SearchInput({
  value = '',
  onChange,
  placeholder,
}: {
  value?: string;
  onChange: (value: string) => void;
  placeholder?: string;
}) {
  const {t} = useTranslation();
  const [text, setText] = useState(value);
  // Cleared from outside ("Show all"): show it, rather than keep filtering by stale text. Adjusted while rendering.
  const [shown, setShown] = useState(value);
  if (value !== shown) {
    setShown(value);
    setText(value);
  }
  const latest = useRef({value, onChange});
  useEffect(() => {
    latest.current = {value, onChange};
  });
  useEffect(() => {
    const timer = setTimeout(() => {
      if (text !== latest.current.value) latest.current.onChange(text);
    }, 300);
    return () => clearTimeout(timer);
  }, [text]);

  return (
    <input
      type="search"
      className="search"
      value={text}
      placeholder={placeholder ?? t('common.search')}
      onChange={(e) => setText(e.target.value)}
    />
  );
}

interface TabsProps<V extends string> {
  value: V;
  options: ReadonlyArray<{value: V; label: ReactNode; icon?: string}>;
  onChange: (value: V) => void;
  /** Links the tabs to their <TabPanel id={id}>. */
  id: string;
  label: string;
  /** A second level, inside a tab's panel: the views of that tab (smaller, so the page's tabs stay the main ones). */
  sub?: boolean;
}

/** A page's own views, as a tab bar under its header. Tabs switch between views, never filter one. */
export function Tabs<V extends string>({
  value,
  options,
  onChange,
  id,
  label,
  sub = false,
}: TabsProps<V>) {
  const select = (index: number) => {
    const option = options[(index + options.length) % options.length];
    if (!option) return;
    onChange(option.value);
    document.getElementById(`${id}-tab-${option.value}`)?.focus();
  };

  return (
    <div
      className={`tabs ${sub ? 'tabs-sub' : 'tabs-page'}`}
      role="tablist"
      aria-label={label}
    >
      {options.map((option, index) => {
        const active = option.value === value;
        return (
          <button
            key={option.value}
            id={`${id}-tab-${option.value}`}
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls={`${id}-panel`}
            tabIndex={active ? 0 : -1}
            className={`tab${active ? ' tab-active' : ''}`}
            onClick={() => onChange(option.value)}
            onKeyDown={(event) => {
              if (event.key === 'ArrowRight') select(index + 1);
              if (event.key === 'ArrowLeft') select(index - 1);
            }}
          >
            {option.icon && <Icon name={option.icon} size={sub ? 16 : 18} />}
            <span>{option.label}</span>
          </button>
        );
      })}
    </div>
  );
}

export function TabPanel({
  id,
  value,
  children,
}: {
  id: string;
  value: string;
  children?: ReactNode;
}) {
  return (
    <div
      id={`${id}-panel`}
      role="tabpanel"
      aria-labelledby={`${id}-tab-${value}`}
      className="tab-panel-page"
    >
      {children}
    </div>
  );
}

export interface PageInfo {
  total: number;
  page: number;
  per_page: number;
}

export function Pager({
  data,
  onPage,
}: {
  data: PageInfo;
  onPage: (page: number) => void;
}) {
  const {t} = useTranslation();
  const pages = Math.max(1, Math.ceil(data.total / data.per_page));
  if (pages <= 1) return null;
  return (
    <nav className="pager" aria-label={t('common.pages')}>
      <Button
        variant="ghost"
        size="sm"
        disabled={data.page <= 1}
        onClick={() => onPage(data.page - 1)}
      >
        {t('common.previous')}
      </Button>
      <span>{t('common.pageOf', {page: data.page, pages})}</span>
      <Button
        variant="ghost"
        size="sm"
        disabled={data.page >= pages}
        onClick={() => onPage(data.page + 1)}
      >
        {t('common.next')}
      </Button>
    </nav>
  );
}

type RowProps = HTMLAttributes<HTMLTableRowElement> & {
  status?: string | null;
  /** Required with `status`: the status in words (tooltip, and read with the first cell). */
  label?: string | null;
};

/** A table row tinted by the item's status: tables have no status column, the colour is the status. */
export function Row({
  status,
  label,
  className = '',
  children,
  ...props
}: RowProps) {
  const tone = status ? toneFor(status) : null;
  const classes = [tone && `row-tone row-tone-${tone}`, className]
    .filter(Boolean)
    .join(' ');
  const cells = Children.toArray(children);
  const first = cells[0];
  if (label && isValidElement<{children?: ReactNode}>(first)) {
    cells[0] = cloneElement(
      first,
      {},
      <span className="visually-hidden">{`${label}: `}</span>,
      first.props.children,
    );
  }
  return (
    <tr className={classes || undefined} title={label ?? undefined} {...props}>
      {cells}
    </tr>
  );
}

/** The last cell of every row: everything the user can click there. */
export function Actions({children}: {children?: ReactNode}) {
  return (
    <td className="actions">
      <div className="row-actions">{children}</div>
    </td>
  );
}

/** The key to a table's row colours, above every tinted table. */
export function RowLegend({
  statuses,
}: {
  statuses: ReadonlyArray<{value: string; label: ReactNode}>;
}) {
  const {t} = useTranslation();
  return (
    <p className="row-legend small muted">
      <span className="row-legend-title">{t('common.rowLegend')}</span>
      {statuses.map((status) => (
        <span
          key={status.value}
          className={`row-legend-item row-legend-${toneFor(status.value)}`}
        >
          {status.label}
        </span>
      ))}
    </p>
  );
}

export interface SelectOption {
  value: string;
  label: ReactNode;
}

export interface Filter {
  name: string;
  label: ReactNode;
  value: string;
  onChange: (value: string) => void;
  options: ReadonlyArray<SelectOption>;
}

/** The filter bar above a table: a search box, then one labelled dropdown per filter, then the table's action. */
export function FilterBar({
  search,
  onSearch,
  searchPlaceholder,
  filters = [],
  children,
}: {
  search?: string;
  onSearch?: (term: string) => void;
  searchPlaceholder?: string;
  filters?: ReadonlyArray<Filter>;
  children?: ReactNode;
}) {
  const {t} = useTranslation();
  return (
    <div className="toolbar">
      {onSearch && (
        <label className="filter-select filter-search">
          <span className="filter-select-label">{t('common.searchLabel')}</span>
          <SearchInput
            value={search ?? ''}
            onChange={onSearch}
            placeholder={searchPlaceholder}
          />
        </label>
      )}
      {filters.map((filter) => (
        <label key={filter.name} className="filter-select">
          <span className="filter-select-label">{filter.label}</span>
          <select
            value={filter.value}
            onChange={(event) => filter.onChange(event.target.value)}
          >
            {filter.options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      ))}
      {children}
    </div>
  );
}

/**
 * The table: the data columns a page names, plus the Actions column added last, so every table calls it the same
 * thing and puts it in the same place. Never write <table> in a page.
 */
export function DataTable<T>({
  columns,
  rows,
  renderRow,
  actions = true,
  busy = false,
}: {
  columns: ReadonlyArray<string>;
  rows: ReadonlyArray<T>;
  renderRow: (row: T, index: number) => ReactNode;
  actions?: boolean;
  busy?: boolean;
}) {
  const {t} = useTranslation();
  const headers = actions ? [...columns, t('common.actions')] : columns;
  return (
    <div className={`table-wrap${busy ? ' is-reloading' : ''}`}>
      <table className="table">
        <thead>
          <tr>
            {headers.map((column, index) => (
              <th
                key={column}
                scope="col"
                className={
                  actions && index === headers.length - 1
                    ? 'col-actions'
                    : undefined
                }
              >
                {column}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{rows.map(renderRow)}</tbody>
      </table>
    </div>
  );
}

interface ModalProps {
  title: string;
  onClose: () => void;
  children?: ReactNode;
}

export function Modal({title, onClose, children}: ModalProps) {
  const {t} = useTranslation();
  const dialog = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);
  useEffect(() => {
    close.current = onClose;
  });
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close.current();
    };
    const opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    document.addEventListener('keydown', onKey);
    document.body.classList.add('admin-modal-open');
    dialog.current
      ?.querySelector<HTMLElement>('input, select, textarea, button')
      ?.focus();
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.classList.remove('admin-modal-open');
      opener?.focus();
    };
  }, []);

  return (
    <div
      className="admin-modal-backdrop"
      onMouseDown={(e) => e.target === e.currentTarget && onClose()}
    >
      <div
        ref={dialog}
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-label={title}
      >
        <header className="modal-header">
          <h2>{title}</h2>
          <button
            type="button"
            className="icon-btn"
            onClick={onClose}
            aria-label={t('common.close')}
          >
            <Icon name="close" size={18} />
          </button>
        </header>
        <div className="admin-modal-body">{children}</div>
      </div>
    </div>
  );
}

/** A modal holding a form: its error on top, its fields in a grid, Cancel and the submit button at the end. */
export function FormModal({
  title,
  onClose,
  onSubmit,
  busy,
  error,
  submitLabel,
  children,
}: ModalProps & {
  onSubmit: () => void;
  busy: boolean;
  error?: string | null;
  submitLabel?: ReactNode;
}) {
  const {t} = useTranslation();
  return (
    <Modal title={title} onClose={onClose}>
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          onSubmit();
        }}
      >
        <Alert kind="error">{error}</Alert>
        <div className="form-grid">{children}</div>
        <div className="form-actions">
          <Button variant="ghost" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" busy={busy}>
            {submitLabel ?? t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

/** A box on a page holding a form section or a group of details. */
export function Card({
  title,
  children,
}: {
  title?: ReactNode;
  children?: ReactNode;
}) {
  return (
    <section className="card">
      {title && <h2 className="card-title">{title}</h2>}
      {children}
    </section>
  );
}

/** What a section shows until the item that builds it lands (docs/pdr/prd-accounting.md, Split). */
export function ComingSoon() {
  const {t} = useTranslation();
  return <EmptyState>{t('common.comingSoon')}</EmptyState>;
}
