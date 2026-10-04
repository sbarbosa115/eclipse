import {useEffect, useState, type ReactNode} from 'react';
import {QuickCreateTerceroModal} from '@/features/quick-create-tercero';
import {fetchContacts, TerceroPicker, type Contact} from '@/entities/tercero';
import {useTranslation} from '@/shared/i18n';
import {DateInput, Field} from '@/shared/ui';
import type {
  DocumentDraft,
  DocumentKind,
  EditorErrors,
  PartyRef,
} from '../model/types';

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
        <TerceroPicker
          activeOnly
          value={value.tercero}
          onChange={onTercero}
          onCreate={() => setCreating(true)}
          labels={{
            placeholder: t('documentEditor.header.terceroPlaceholder'),
            minChars: (count) => t('common.combo.minChars', {count}),
            searching: t('common.combo.searching'),
            none: t('common.combo.none'),
            failed: t('common.combo.failed'),
          }}
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
        <DateInput value={value.issue_date} onChange={onIssueDate} />
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
