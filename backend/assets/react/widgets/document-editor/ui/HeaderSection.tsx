import {useEffect, useState, type ReactNode} from 'react';
import {QuickCreateTerceroModal} from '@/features/quick-create-tercero';
import {
  fetchContacts,
  identificationLabel,
  searchTerceros,
  type Contact,
  type TerceroSummary,
} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {Field} from '@/shared/ui';
import type {
  DocumentDraft,
  DocumentKind,
  EditorErrors,
  PartyRef,
} from '../model/types';
import {SearchCombobox, type ComboOption} from './SearchCombobox';

const TERCERO_MIN_CHARS = 3;

async function findTerceros(
  term: string,
): Promise<ComboOption<TerceroSummary>[]> {
  const page = await searchTerceros({q: term, active: '1', per_page: 10});
  return page.items.map((tercero) => ({
    id: tercero.id,
    label: tercero.display_name,
    detail: identificationLabel(tercero),
    value: tercero,
  }));
}

/** Tipo, Número, the tercero (search from 3 characters, or create one), its contact and Fecha de elaboración. */
export function HeaderSection({
  kind,
  value,
  errors,
  onTercero,
  onContact,
  onIssueDate,
  extra,
}: {
  kind: DocumentKind;
  value: DocumentDraft;
  errors: EditorErrors;
  onTercero: (tercero: PartyRef | null) => void;
  onContact: (contactId: string | null) => void;
  onIssueDate: (date: string) => void;
  extra?: ReactNode;
}) {
  const {t} = useTranslation();
  const purchase = kind === 'purchase_invoice';
  const [creating, setCreating] = useState(false);
  const [contacts, setContacts] = useState<{
    terceroId: string | null;
    items: Contact[];
  }>({terceroId: null, items: []});
  const terceroId = value.tercero?.id ?? null;

  useEffect(() => {
    if (!terceroId) return;
    let cancelled = false;
    fetchContacts(terceroId)
      .then((items) => {
        if (!cancelled) setContacts({terceroId, items});
      })
      // Without the list the document still saves: the contact is optional.
      .catch(() => {
        if (!cancelled) setContacts({terceroId, items: []});
      });
    return () => {
      cancelled = true;
    };
  }, [terceroId]);

  const shownContacts = contacts.terceroId === terceroId ? contacts.items : [];

  return (
    <div className="doc-header form-grid">
      <Field label={t('documentEditor.header.type')}>
        <input value={value.type_label} readOnly />
      </Field>
      <Field label={t('documentEditor.header.number')}>
        <input
          value={value.number ?? t('documentEditor.header.numberPending')}
          readOnly
        />
      </Field>
      <Field
        label={t(
          purchase
            ? 'documentEditor.header.proveedor'
            : 'documentEditor.header.cliente',
        )}
        hint={t('documentEditor.header.terceroHint')}
        error={errors['tercero']}
      >
        <SearchCombobox<TerceroSummary>
          selectedLabel={value.tercero?.name ?? ''}
          minChars={TERCERO_MIN_CHARS}
          search={findTerceros}
          placeholder={t('documentEditor.header.terceroPlaceholder')}
          onSelect={(option) =>
            onTercero({id: option.id, name: option.value.display_name})
          }
          onClear={() => onTercero(null)}
          onCreate={() => setCreating(true)}
        />
      </Field>
      <Field
        label={t('documentEditor.header.contact')}
        error={errors['contact_id']}
        optional
      >
        <select
          value={value.contact_id ?? ''}
          disabled={!terceroId}
          onChange={(e) => onContact(e.target.value || null)}
        >
          <option value="">
            {terceroId
              ? t('documentEditor.header.noContact')
              : t('documentEditor.header.chooseTerceroFirst')}
          </option>
          {shownContacts.map((contact) => (
            <option key={contact.id} value={contact.id}>
              {contact.name}
            </option>
          ))}
        </select>
      </Field>
      <Field
        label={t('documentEditor.header.issueDate')}
        error={errors['issue_date']}
      >
        <input
          type="date"
          value={value.issue_date}
          onChange={(e) => onIssueDate(e.target.value)}
        />
      </Field>
      {extra}
      {creating && (
        <QuickCreateTerceroModal
          defaultRole={purchase ? 'proveedor' : 'cliente'}
          onClose={() => setCreating(false)}
          onCreated={(tercero) => {
            setCreating(false);
            onTercero({id: tercero.id, name: tercero.display_name});
          }}
        />
      )}
    </div>
  );
}
