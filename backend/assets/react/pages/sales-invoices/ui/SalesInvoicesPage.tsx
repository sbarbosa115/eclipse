import {Route, Routes, useParams} from 'react-router-dom';
import {SalesInvoiceEditor} from './SalesInvoiceEditor';
import {SalesInvoicesList} from './SalesInvoicesList';

/** Another invoice is another form: going from one to the next (duplicate) starts the editor afresh. */
function EditorForRoute() {
  const {id} = useParams();
  return <SalesInvoiceEditor key={id ?? 'new'} />;
}

/** Facturas de venta: the list at /facturas-venta, a new one at /facturas-venta/nueva, one invoice at /facturas-venta/:id. */
export function SalesInvoicesPage() {
  return (
    <Routes>
      <Route index element={<SalesInvoicesList />} />
      <Route path="nueva" element={<EditorForRoute />} />
      <Route path=":id" element={<EditorForRoute />} />
    </Routes>
  );
}
