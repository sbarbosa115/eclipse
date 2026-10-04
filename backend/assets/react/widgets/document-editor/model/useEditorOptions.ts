import {useEffect, useState} from 'react';
import {listTaxes, type Tax} from '@/entities/product';
import {listPaymentMethods, type PaymentMethod} from '../api/documentEditorApi';
import type {DocumentKind} from './types';

export interface EditorOptions {
  status: 'loading' | 'ready' | 'failed';
  /** Every active tax: what the preview and the lines' current taxes are read from. */
  chargeTaxes: Tax[];
  withholdingTaxes: Tax[];
  /** What may be newly chosen: the active taxes in force on the document's date (F5). */
  inForce: {charge: Tax[]; withholding: Tax[]};
  methods: PaymentMethod[];
}

const ISO_DAY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * What the form picks from: the company's active taxes, those in force on the document's date (asked again when the
 * date changes) and, except on a quotation, its payment methods.
 */
export function useEditorOptions(
  kind: DocumentKind,
  issueDate: string,
): EditorOptions {
  const [options, setOptions] = useState<Omit<EditorOptions, 'inForce'>>({
    status: 'loading',
    chargeTaxes: [],
    withholdingTaxes: [],
    methods: [],
  });
  const [dated, setDated] = useState<{
    on: string;
    charge: Tax[];
    withholding: Tax[];
  } | null>(null);
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

  // While the date is being typed (not a date yet) the last answer stays.
  const on = ISO_DAY.test(issueDate) ? issueDate : null;
  useEffect(() => {
    if (on === null) return;
    let cancelled = false;
    Promise.all([listTaxes('charge', on), listTaxes('withholding', on)])
      .then(([charge, withholding]) => {
        if (!cancelled) setDated({on, charge, withholding});
      })
      // Without the dated lists the form offers every active tax; the server still refuses one not in force.
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [on]);

  return {
    ...options,
    inForce: dated
      ? {charge: dated.charge, withholding: dated.withholding}
      : {charge: options.chargeTaxes, withholding: options.withholdingTaxes},
  };
}

/** The taxes a select offers: those in force, plus the one the line already has (a draft keeps its own). */
export function offeredTaxes(
  inForce: Tax[],
  all: Tax[],
  chosen: string | null,
): Tax[] {
  if (!chosen || inForce.some((tax) => tax.id === chosen)) return inForce;
  return [...inForce, ...all.filter((tax) => tax.id === chosen)];
}
