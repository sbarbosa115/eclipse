import {useEffect, useState} from 'react';
import {listTaxes, type Tax} from '@/entities/product';
import {apiGet, type Schema} from '@/shared/api';

type PaymentMethod = Schema<'PaymentMethodOutput'>;

/** The taxes and payment methods the page checks a draft with (the editor loads its own to show them). */
export function usePurchaseOptions(): {
  taxes: Tax[];
  methods: PaymentMethod[];
} {
  const [options, setOptions] = useState<{
    taxes: Tax[];
    methods: PaymentMethod[];
  }>({taxes: [], methods: []});
  useEffect(() => {
    let cancelled = false;
    Promise.all([
      listTaxes('charge'),
      listTaxes('withholding'),
      apiGet<{items: PaymentMethod[]}>('/payment-methods'),
    ])
      .then(([charge, withholding, methods]) => {
        if (!cancelled) {
          setOptions({taxes: [...charge, ...withholding], methods: methods.items});
        }
      })
      // The editor says when its options failed to load; the server checks the draft anyway.
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, []);
  return options;
}
