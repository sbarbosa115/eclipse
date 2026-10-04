import {Route, Routes} from 'react-router-dom';
import {CashReceiptsList} from './CashReceiptsList';
import {CashReceiptView} from './CashReceiptView';
import {NewCashReceipt} from './NewCashReceipt';
import './cashReceipts.css';

/** Recibos de caja: the list at /recibos-caja, a new one at /recibos-caja/nuevo, one receipt at /recibos-caja/:id. */
export function CashReceiptsPage() {
  return (
    <Routes>
      <Route index element={<CashReceiptsList />} />
      <Route path="nuevo" element={<NewCashReceipt />} />
      <Route path=":id" element={<CashReceiptView />} />
    </Routes>
  );
}
