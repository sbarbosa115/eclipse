import {useCallback, useEffect, useState} from 'react';
import {useSearchParams} from 'react-router-dom';
import {
  deactivateProduct,
  deleteProduct,
  listProducts,
  reactivateProduct,
  useProductOptions,
  type Product,
  type ProductPage,
} from '@/entities/product';
import {useSession} from '@/entities/session';
import {ApiError} from '@/shared/api';
import {useTranslation} from '@/shared/i18n';
import {formatMoney} from '@/shared/lib';
import {
  ActionButton,
  Actions,
  Alert,
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  Loading,
  Modal,
  PageHeader,
  Pager,
  Row,
} from '@/shared/ui';
import {CategoriesModal} from './CategoriesModal';
import {ProductFormModal} from './ProductFormModal';
import './products.css';

type Load =
  | {status: 'loading'}
  | {status: 'error'}
  | {status: 'ready'; page: ProductPage};

type Dialog =
  | {kind: 'form'; product: Product | null}
  | {kind: 'delete'; product: Product; inUse: boolean}
  | {kind: 'categories'}
  | null;

/**
 * Productos y servicios (§4.3): search and filter the catalog, and create, edit, deactivate or delete (when no
 * document used it). The accountant reads; the owner and billing users write.
 */
export function ProductsPage() {
  const {t} = useTranslation();
  const {session} = useSession();
  const canWrite = session?.role === 'owner' || session?.role === 'billing';
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const type = params.get('type') ?? '';
  const active = params.get('active') ?? '';
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1);

  const options = useProductOptions();
  const [load, setLoad] = useState<Load>({status: 'loading'});
  const [reloadTick, setReloadTick] = useState(0);
  const [dialog, setDialog] = useState<Dialog>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    listProducts({q, type, active, page})
      .then((found) => {
        if (!cancelled) setLoad({status: 'ready', page: found});
      })
      .catch(() => {
        if (!cancelled) setLoad({status: 'error'});
      });
    return () => {
      cancelled = true;
    };
  }, [q, type, active, page, reloadTick]);

  const refresh = useCallback(() => setReloadTick((n) => n + 1), []);

  const setFilter = (name: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value === '') next.delete(name);
    else next.set(name, value);
    next.delete('page');
    setParams(next, {replace: true});
  };
  const clearFilters = () => setParams({}, {replace: true});
  const filtered = q !== '' || type !== '' || active !== '';

  const toggle = async (product: Product) => {
    setBusyId(product.id);
    setNotice(null);
    setFailure(null);
    try {
      if (product.active) {
        await deactivateProduct(product.id);
        setNotice(t('catalog.deactivate.done', {name: product.name}));
      } else {
        await reactivateProduct(product.id);
        setNotice(t('catalog.deactivate.reactivated', {name: product.name}));
      }
      refresh();
    } catch {
      setFailure(t('common.errors.unexpected'));
    }
    setBusyId(null);
  };

  const remove = async (product: Product) => {
    setBusyId(product.id);
    try {
      await deleteProduct(product.id);
      setDialog(null);
      setNotice(t('catalog.delete.done', {name: product.name}));
      refresh();
    } catch (error) {
      if (error instanceof ApiError && error.code === 'product_in_use') {
        setDialog({kind: 'delete', product, inUse: true});
      } else {
        setDialog(null);
        setFailure(t('common.errors.unexpected'));
      }
    }
    setBusyId(null);
  };

  const unitName = (code: string) =>
    options.units.find((unit) => unit.code === code)?.name ?? code;

  const body = () => {
    if (load.status === 'loading') return <Loading />;
    if (load.status === 'error') {
      return <ErrorState message={t('common.loadFailed')} onRetry={refresh} />;
    }
    const data = load.page;
    if (data.items.length === 0) {
      return filtered ? (
        <EmptyState
          action={
            <Button variant="ghost" onClick={clearFilters}>
              {t('common.showAll')}
            </Button>
          }
        >
          {t('catalog.empty.filtered')}
        </EmptyState>
      ) : (
        <EmptyState
          action={
            canWrite && (
              <Button onClick={() => setDialog({kind: 'form', product: null})}>
                {t('catalog.new')}
              </Button>
            )
          }
        >
          {t('catalog.empty.title')}
        </EmptyState>
      );
    }
    return (
      <>
        <DataTable
          columns={[
            t('catalog.columns.code'),
            t('catalog.columns.name'),
            t('catalog.columns.type'),
            t('catalog.columns.unit'),
            t('catalog.columns.price'),
          ]}
          rows={data.items}
          renderRow={(product) => (
            <Row
              key={product.id}
              status={product.active ? null : 'cancelled'}
              label={product.active ? null : t('catalog.inactive')}
            >
              <td>{product.code}</td>
              <td>
                {product.name}
                {product.category_name && (
                  <span className="product-price-note">
                    {product.category_name}
                  </span>
                )}
              </td>
              <td>{t(`catalog.type.${product.type}`)}</td>
              <td>{unitName(product.unit_code)}</td>
              <td>
                {formatMoney(product.sale_price)}
                {product.price_includes_tax && (
                  <span className="product-price-note">
                    {t('catalog.priceIncludesTax')}
                    {' · '}
                    {t('catalog.netOfTax', {
                      amount: formatMoney(product.unit_price_net_of_tax),
                    })}
                  </span>
                )}
              </td>
              <Actions>
                <ActionButton
                  action={canWrite ? 'edit' : 'open'}
                  onClick={() => setDialog({kind: 'form', product})}
                >
                  {t(
                    canWrite ? 'catalog.actions.edit' : 'catalog.actions.view',
                  )}
                </ActionButton>
                {canWrite && (
                  <>
                    <ActionButton
                      action={product.active ? 'danger' : 'confirm'}
                      busy={busyId === product.id}
                      onClick={() => toggle(product)}
                    >
                      {t(
                        product.active
                          ? 'catalog.actions.deactivate'
                          : 'catalog.actions.reactivate',
                      )}
                    </ActionButton>
                    <ActionButton
                      action="danger"
                      onClick={() =>
                        setDialog({kind: 'delete', product, inUse: false})
                      }
                    >
                      {t('catalog.actions.delete')}
                    </ActionButton>
                  </>
                )}
              </Actions>
            </Row>
          )}
        />
        <Pager
          data={data}
          onPage={(next) => {
            const nextParams = new URLSearchParams(params);
            nextParams.set('page', String(next));
            setParams(nextParams, {replace: true});
          }}
        />
      </>
    );
  };

  return (
    <>
      <PageHeader
        title={t('shell.nav.products')}
        subtitle={t('catalog.intro')}
        actions={
          <>
            <Button
              variant="secondary"
              onClick={() => setDialog({kind: 'categories'})}
            >
              {t('catalog.categories')}
            </Button>
            {canWrite && (
              <Button onClick={() => setDialog({kind: 'form', product: null})}>
                {t('catalog.new')}
              </Button>
            )}
          </>
        }
      />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <Alert kind="error" onDismiss={() => setFailure(null)}>
        {failure}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(term) => setFilter('q', term)}
        searchPlaceholder={t('catalog.searchPlaceholder')}
        filters={[
          {
            name: 'type',
            label: t('catalog.type.label'),
            value: type,
            onChange: (value) => setFilter('type', value),
            options: [
              {value: '', label: t('catalog.type.all')},
              {value: 'producto', label: t('catalog.type.producto')},
              {value: 'servicio', label: t('catalog.type.servicio')},
            ],
          },
          {
            name: 'active',
            label: t('catalog.status.label'),
            value: active,
            onChange: (value) => setFilter('active', value),
            options: [
              {value: '', label: t('catalog.status.all')},
              {value: '1', label: t('catalog.status.active')},
              {value: '0', label: t('catalog.status.inactive')},
            ],
          },
        ]}
      />
      {body()}
      {dialog?.kind === 'form' && (
        <ProductFormModal
          product={dialog.product}
          options={options}
          readOnly={!canWrite}
          onClose={() => setDialog(null)}
          onSaved={(_saved, created) => {
            setDialog(null);
            setNotice(
              t(created ? 'catalog.form.created' : 'catalog.form.saved'),
            );
            refresh();
          }}
        />
      )}
      {dialog?.kind === 'categories' && (
        <CategoriesModal
          canWrite={canWrite}
          onClose={() => {
            setDialog(null);
            options.reload();
            refresh();
          }}
        />
      )}
      {dialog?.kind === 'delete' && (
        <Modal
          title={t('catalog.delete.title')}
          onClose={() => setDialog(null)}
        >
          {dialog.inUse ? (
            <Alert kind="warning">
              {t('catalog.delete.inUse', {name: dialog.product.name})}
            </Alert>
          ) : (
            <p>{t('catalog.delete.confirm', {name: dialog.product.name})}</p>
          )}
          <div className="form-actions">
            <Button variant="ghost" onClick={() => setDialog(null)}>
              {t('common.cancel')}
            </Button>
            {dialog.inUse ? (
              <Button
                onClick={async () => {
                  const product = dialog.product;
                  setDialog(null);
                  await toggle(product);
                }}
              >
                {t('catalog.actions.deactivate')}
              </Button>
            ) : (
              <Button
                busy={busyId === dialog.product.id}
                onClick={() => remove(dialog.product)}
              >
                {t('catalog.actions.delete')}
              </Button>
            )}
          </div>
        </Modal>
      )}
    </>
  );
}
