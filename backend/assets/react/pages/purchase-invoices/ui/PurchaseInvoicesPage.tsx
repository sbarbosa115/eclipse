import {Route, Routes, useParams} from 'react-router-dom';
import {PurchaseInvoiceEditorPage} from './PurchaseInvoiceEditorPage';
import {PurchaseInvoiceListPage} from './PurchaseInvoiceListPage';
import './purchaseInvoices.css';

/** One form per invoice: moving to another (Duplicar) starts afresh. */
function EditorRoute() {
  const {id} = useParams();
  return <PurchaseInvoiceEditorPage key={id ?? 'nueva'} />;
}

/** Facturas de compra: the list at /facturas-compra, the form at /facturas-compra/nueva and /facturas-compra/:id. */
export function PurchaseInvoicesPage() {
  return (
    <Routes>
      <Route index element={<PurchaseInvoiceListPage />} />
      <Route path="nueva" element={<EditorRoute />} />
      <Route path=":id" element={<EditorRoute />} />
    </Routes>
  );
}
