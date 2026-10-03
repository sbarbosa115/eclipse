import {Route, Routes} from 'react-router-dom';
import {TerceroEditor} from './TerceroEditor';
import {TercerosList} from './TercerosList';

/** Terceros: the list at /terceros, the full form at /terceros/nuevo and /terceros/:id. */
export function TercerosPage() {
  return (
    <Routes>
      <Route index element={<TercerosList />} />
      <Route path="nuevo" element={<TerceroEditor />} />
      <Route path=":id" element={<TerceroEditor />} />
    </Routes>
  );
}
