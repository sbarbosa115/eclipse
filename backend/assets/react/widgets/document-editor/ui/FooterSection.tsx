import {useId, type ReactNode} from 'react';
import {useTranslation} from '@/shared/i18n';
import {Field, IconButton} from '@/shared/ui';
import type {Attachment, EditorErrors} from '../model/types';

/** Observaciones and the attachments, which the page uploads and removes (the editor only lists them). */
export function FooterSection({
  notes,
  attachments,
  errors,
  readOnly,
  onNotes,
  onAttach,
  onRemoveAttachment,
  extra,
}: {
  notes: string;
  attachments: Attachment[];
  errors: EditorErrors;
  readOnly: boolean;
  onNotes: (notes: string) => void;
  onAttach?: (files: File[]) => void;
  onRemoveAttachment?: (id: string) => void;
  extra?: ReactNode;
}) {
  const {t} = useTranslation();
  const titleId = useId();
  return (
    <div className="doc-footer">
      <Field
        label={t('documentEditor.footer.notes')}
        error={errors['notes']}
        optional
      >
        <textarea
          rows={3}
          maxLength={2000}
          value={notes}
          onChange={(e) => onNotes(e.target.value)}
        />
      </Field>
      <section aria-labelledby={titleId}>
        <h3 id={titleId} className="admin-field-label">
          {t('documentEditor.footer.attachments')}
        </h3>
        {attachments.length === 0 ? (
          <p className="small muted">
            {t('documentEditor.footer.noAttachments')}
          </p>
        ) : (
          <ul className="doc-attachments">
            {attachments.map((file) => (
              <li key={file.id}>
                <span className="break-any">{file.name}</span>
                {!readOnly && onRemoveAttachment && (
                  <IconButton
                    icon="trash"
                    label={t('documentEditor.footer.removeAttachment', {
                      name: file.name,
                    })}
                    onClick={() => onRemoveAttachment(file.id)}
                  />
                )}
              </li>
            ))}
          </ul>
        )}
        {!readOnly && onAttach && (
          <Field
            label={t('documentEditor.footer.attach')}
            error={errors['attachments']}
          >
            <input
              type="file"
              multiple
              onChange={(e) => {
                const files = Array.from(e.target.files ?? []);
                if (files.length > 0) onAttach(files);
                e.target.value = '';
              }}
            />
          </Field>
        )}
      </section>
      {extra}
    </div>
  );
}
