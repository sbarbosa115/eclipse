import {useEffect, useState, type FormEvent} from 'react';
import {
  createCategory,
  fieldErrors,
  listCategories,
  renameCategory,
  type ProductCategory,
} from '@/entities/product';
import {useTranslation} from '@/shared/i18n';
import {
  ActionButton,
  Actions,
  Alert,
  Button,
  DataTable,
  EmptyState,
  Field,
  Loading,
  Modal,
} from '@/shared/ui';

/**
 * The flat list of categories (§9 Q20): add one, rename one. The accountant sees the list and nothing to change.
 */
export function CategoriesModal({
  canWrite,
  onClose,
}: {
  canWrite: boolean;
  onClose: () => void;
}) {
  const {t} = useTranslation();
  const [categories, setCategories] = useState<ProductCategory[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [name, setName] = useState('');
  const [editing, setEditing] = useState<{id: string; name: string} | null>(
    null,
  );
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    listCategories()
      .then((found) => {
        if (!cancelled) setCategories(found);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const byName = (list: ProductCategory[]) =>
    [...list].sort((a, b) => a.name.localeCompare(b.name, 'es'));

  const add = async (event: FormEvent) => {
    event.preventDefault();
    if (name.trim() === '') {
      setError(t('catalog.validation.required'));
      return;
    }
    setBusy(true);
    try {
      const created = await createCategory(name.trim());
      setCategories((list) => byName([...(list ?? []), created]));
      setName('');
      setError(null);
    } catch (e) {
      setError(fieldErrors(e).name ?? t('common.errors.unexpected'));
    }
    setBusy(false);
  };

  const save = async () => {
    if (!editing) return;
    if (editing.name.trim() === '') {
      setError(t('catalog.validation.required'));
      return;
    }
    setBusy(true);
    try {
      const renamed = await renameCategory(editing.id, editing.name.trim());
      setCategories((list) =>
        byName((list ?? []).map((c) => (c.id === renamed.id ? renamed : c))),
      );
      setEditing(null);
      setError(null);
    } catch (e) {
      setError(fieldErrors(e).name ?? t('common.errors.unexpected'));
    }
    setBusy(false);
  };

  return (
    <Modal title={t('catalog.categoriesModal.title')} onClose={onClose}>
      <Alert kind="error">{failed ? t('common.loadFailed') : null}</Alert>
      {canWrite && (
        <form className="form" onSubmit={add} noValidate>
          <Field
            label={t('catalog.categoriesModal.name')}
            error={editing ? null : error}
          >
            <input
              value={name}
              maxLength={100}
              onChange={(e) => setName(e.target.value)}
            />
          </Field>
          <Button type="submit" busy={busy && !editing}>
            {t('catalog.categoriesModal.add')}
          </Button>
        </form>
      )}
      {categories === null && !failed && <Loading />}
      {categories?.length === 0 && (
        <EmptyState>{t('catalog.categoriesModal.empty')}</EmptyState>
      )}
      {categories !== null && categories.length > 0 && (
        <DataTable
          columns={[
            t('catalog.columns.name'),
            t('catalog.categoriesModal.products'),
          ]}
          rows={categories}
          actions={canWrite}
          renderRow={(category) => (
            <tr key={category.id}>
              <td>
                {editing?.id === category.id ? (
                  <Field
                    label={t('catalog.categoriesModal.name')}
                    error={error}
                  >
                    <input
                      value={editing.name}
                      maxLength={100}
                      onChange={(e) =>
                        setEditing({id: category.id, name: e.target.value})
                      }
                    />
                  </Field>
                ) : (
                  category.name
                )}
              </td>
              <td>{category.product_count}</td>
              {canWrite && (
                <Actions>
                  {editing?.id === category.id ? (
                    <>
                      <ActionButton action="confirm" busy={busy} onClick={save}>
                        {t('catalog.categoriesModal.save')}
                      </ActionButton>
                      <ActionButton
                        action="danger"
                        onClick={() => {
                          setEditing(null);
                          setError(null);
                        }}
                      >
                        {t('common.cancel')}
                      </ActionButton>
                    </>
                  ) : (
                    <ActionButton
                      action="edit"
                      onClick={() => {
                        setEditing({id: category.id, name: category.name});
                        setError(null);
                      }}
                    >
                      {t('catalog.categoriesModal.rename')}
                    </ActionButton>
                  )}
                </Actions>
              )}
            </tr>
          )}
        />
      )}
      <div className="form-actions">
        <Button variant="ghost" onClick={onClose}>
          {t('catalog.categoriesModal.close')}
        </Button>
      </div>
    </Modal>
  );
}
