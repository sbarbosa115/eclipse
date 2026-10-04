import {Route, Routes} from 'react-router-dom';
import {SupplierPaymentsList} from './SupplierPaymentsList';
import {SupplierPaymentView} from './SupplierPaymentView';
import {NewSupplierPayment} from './NewSupplierPayment';
import './supplierPayments.css';

/** Recibos de pago: the list at /recibos-pago, a new one at /recibos-pago/nuevo, one payment at /recibos-pago/:id. */
export function SupplierPaymentsPage() {
  return (
    <Routes>
      <Route index element={<SupplierPaymentsList />} />
      <Route path="nuevo" element={<NewSupplierPayment />} />
      <Route path=":id" element={<SupplierPaymentView />} />
    </Routes>
  );
}
