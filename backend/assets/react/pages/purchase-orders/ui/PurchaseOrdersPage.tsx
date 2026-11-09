import {Route, Routes, useParams} from 'react-router-dom';
import {PurchaseOrderEditor} from './PurchaseOrderEditor';
import {PurchaseOrdersList} from './PurchaseOrdersList';

/** Another order is another form: going from one to the next (duplicate) starts the editor afresh. */
function EditorForRoute() {
  const {id} = useParams();
  return <PurchaseOrderEditor key={id ?? 'new'} />;
}

/**
 * Órdenes de compra (observations PRD §4.8): the list at /ordenes-compra, a new one at /ordenes-compra/nueva, one
 * order at /ordenes-compra/:id.
 */
export function PurchaseOrdersPage() {
  return (
    <Routes>
      <Route index element={<PurchaseOrdersList />} />
      <Route path="nueva" element={<EditorForRoute />} />
      <Route path=":id" element={<EditorForRoute />} />
    </Routes>
  );
}
