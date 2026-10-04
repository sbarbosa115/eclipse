import {Route, Routes, useParams} from 'react-router-dom';
import {QuotationEditor} from './QuotationEditor';
import {QuotationsList} from './QuotationsList';

/** Another quotation is another form: going from one to the next (duplicate) starts the editor afresh. */
function EditorForRoute() {
  const {id} = useParams();
  return <QuotationEditor key={id ?? 'new'} />;
}

/** Cotizaciones: the list at /cotizaciones, a new one at /cotizaciones/nueva, one quotation at /cotizaciones/:id. */
export function QuotationsPage() {
  return (
    <Routes>
      <Route index element={<QuotationsList />} />
      <Route path="nueva" element={<EditorForRoute />} />
      <Route path=":id" element={<EditorForRoute />} />
    </Routes>
  );
}
