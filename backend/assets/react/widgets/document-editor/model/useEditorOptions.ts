import {useEffect, useState} from 'react';
import {listTaxes, type Tax} from '@/entities/product';
import {listPaymentMethods, type PaymentMethod} from '../api/documentEditorApi';
import type {DocumentKind} from './types';

export interface EditorOptions {
  status: 'loading' | 'ready' | 'failed';
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
  methods: PaymentMethod[];
}

/** What the form picks from: the company's active taxes and, except on a quotation, its payment methods. */
export function useEditorOptions(kind: DocumentKind): EditorOptions {
  const [options, setOptions] = useState<EditorOptions>({
    status: 'loading',
    chargeTaxes: [],
    withholdingTaxes: [],
    methods: [],
  });
  const withPayments = kind !== 'quotation';

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      listTaxes('charge'),
      listTaxes('withholding'),
      withPayments ? listPaymentMethods() : Promise.resolve([]),
    ])
      .then(([chargeTaxes, withholdingTaxes, methods]) => {
        if (cancelled) return;
        setOptions({status: 'ready', chargeTaxes, withholdingTaxes, methods});
      })
      .catch(() => {
        if (!cancelled) setOptions((o) => ({...o, status: 'failed'}));
      });
    return () => {
      cancelled = true;
    };
  }, [withPayments]);

  return options;
}
