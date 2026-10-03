// The Spanish catalog, one file per namespace. Each namespace belongs to one item of the accounting split
// (docs/pdr/prd-accounting.md): an item writes only its own file; this index is item 0's and lists them all.
import common from './common.json';
import auth from './auth.json';
import shell from './shell.json';
import settings from './settings.json';
import company from './company.json';
import access from './access.json';
import ledger from './ledger.json';
import taxes from './taxes.json';
import paymentMethods from './paymentMethods.json';
import terceros from './terceros.json';
import catalog from './catalog.json';
import documentEditor from './documentEditor.json';
import salesInvoice from './salesInvoice.json';
import purchaseInvoice from './purchaseInvoice.json';
import quotation from './quotation.json';
import cashReceipt from './cashReceipt.json';
import supplierPayment from './supplierPayment.json';
import reports from './reports.json';

export const es = {
  common, // item 0
  auth, // item 0
  shell, // item 0
  settings, // item 0
  company, // item 2 company
  access, // item 1 access
  ledger, // item 3 ledger
  taxes, // item 4 taxes-payments
  paymentMethods, // item 4 taxes-payments
  terceros, // item 5 terceros
  catalog, // item 6 catalog
  documentEditor, // item 7 document-editor
  salesInvoice, // item 8 sales-invoice
  purchaseInvoice, // item 9 purchase-invoice
  quotation, // item 10 quotation
  cashReceipt, // item 11 cash-receipt
  supplierPayment, // item 12 supplier-payment
  reports, // item 13 reports
};
