import {useEffect, useState} from 'react';
import {listTaxes, type Tax} from '../api/productApi';

/** The company's active charge and withholding taxes, for the pickers of a product. */
export function useTaxOptions(): {
  ready: boolean;
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
} {
  const [state, setState] = useState<{
    ready: boolean;
    chargeTaxes: Tax[];
    withholdingTaxes: Tax[];
  }>({ready: false, chargeTaxes: [], withholdingTaxes: []});

  useEffect(() => {
    let cancelled = false;
    Promise.all([listTaxes('charge'), listTaxes('withholding')])
      .then(([chargeTaxes, withholdingTaxes]) => {
        if (!cancelled) setState({ready: true, chargeTaxes, withholdingTaxes});
      })
      // Without the lists the selects keep only "the company's": the defaults still apply on the server.
      .catch(() => {
        if (!cancelled) setState((s) => ({...s, ready: true}));
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
