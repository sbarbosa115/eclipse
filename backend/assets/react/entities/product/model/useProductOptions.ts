import {useCallback, useEffect, useState} from 'react';
import {
  listCategories,
  listTaxes,
  listUnits,
  type ProductCategory,
  type Tax,
  type Unit,
} from '../api/productApi';

export interface ProductOptions {
  status: 'loading' | 'ready' | 'failed';
  units: Unit[];
  categories: ProductCategory[];
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
  reload: () => void;
}

/** What a product form picks from: units, categories and the company's active taxes. */
export function useProductOptions(): ProductOptions {
  const [state, setState] = useState<Omit<ProductOptions, 'reload'>>({
    status: 'loading',
    units: [],
    categories: [],
    chargeTaxes: [],
    withholdingTaxes: [],
  });
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      listUnits(),
      listCategories(),
      listTaxes('charge'),
      listTaxes('withholding'),
    ])
      .then(([units, categories, chargeTaxes, withholdingTaxes]) => {
        if (cancelled) return;
        setState({
          status: 'ready',
          units,
          categories,
          chargeTaxes,
          withholdingTaxes,
        });
      })
      .catch(() => {
        if (!cancelled) setState((s) => ({...s, status: 'failed'}));
      });
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  const reload = useCallback(() => setAttempt((n) => n + 1), []);
  return {...state, reload};
}
