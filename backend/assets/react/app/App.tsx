import {lazy, Suspense} from 'react';
import {BrowserRouter, Route, Routes} from 'react-router-dom';
import {SessionProvider} from '@/entities/session';
import {NotFoundPage} from '@/pages/not-found';
import {SignInPage} from '@/pages/sign-in';
import {SignUpPage} from '@/pages/sign-up';
import {FullPageLoading, ThemedRoot} from '@/shared/ui';
import {AppShell} from '@/widgets/app-shell';

// Every page behind the sign-in is a lazy chunk. Paths are Spanish, like everything the user reads.
const DashboardPage = lazy(() =>
  import('@/pages/dashboard').then((m) => ({default: m.DashboardPage})),
);
const QuotationsPage = lazy(() =>
  import('@/pages/quotations').then((m) => ({default: m.QuotationsPage})),
);
const SalesInvoicesPage = lazy(() =>
  import('@/pages/sales-invoices').then((m) => ({
    default: m.SalesInvoicesPage,
  })),
);
const CashReceiptsPage = lazy(() =>
  import('@/pages/cash-receipts').then((m) => ({default: m.CashReceiptsPage})),
);
const PurchaseInvoicesPage = lazy(() =>
  import('@/pages/purchase-invoices').then((m) => ({
    default: m.PurchaseInvoicesPage,
  })),
);
const SupplierPaymentsPage = lazy(() =>
  import('@/pages/supplier-payments').then((m) => ({
    default: m.SupplierPaymentsPage,
  })),
);
const LedgerPage = lazy(() =>
  import('@/pages/ledger').then((m) => ({default: m.LedgerPage})),
);
const ReportsPage = lazy(() =>
  import('@/pages/reports').then((m) => ({default: m.ReportsPage})),
);
const TercerosPage = lazy(() =>
  import('@/pages/terceros').then((m) => ({default: m.TercerosPage})),
);
const ProductsPage = lazy(() =>
  import('@/pages/products').then((m) => ({default: m.ProductsPage})),
);
const SettingsPage = lazy(() =>
  import('@/pages/settings').then((m) => ({default: m.SettingsPage})),
);
// Development only: the shared document form on a page of its own (item 7), until the document pages mount it.
const DocumentEditorDemo =
  process.env.NODE_ENV === 'production'
    ? null
    : lazy(() =>
        import('@/widgets/document-editor').then((m) => ({
          default: m.DocumentEditorDemo,
        })),
      );

export function App() {
  return (
    <BrowserRouter>
      <ThemedRoot>
        <SessionProvider>
          <Suspense fallback={<FullPageLoading />}>
            <Routes>
              <Route path="ingresar" element={<SignInPage />} />
              <Route path="registro" element={<SignUpPage />} />
              <Route element={<AppShell />}>
                <Route index element={<DashboardPage />} />
                <Route path="cotizaciones/*" element={<QuotationsPage />} />
                <Route
                  path="facturas-venta/*"
                  element={<SalesInvoicesPage />}
                />
                <Route path="recibos-caja/*" element={<CashReceiptsPage />} />
                <Route
                  path="facturas-compra/*"
                  element={<PurchaseInvoicesPage />}
                />
                <Route
                  path="recibos-pago/*"
                  element={<SupplierPaymentsPage />}
                />
                <Route path="contabilidad/*" element={<LedgerPage />} />
                <Route path="reportes/*" element={<ReportsPage />} />
                <Route path="terceros/*" element={<TercerosPage />} />
                <Route path="productos/*" element={<ProductsPage />} />
                <Route path="configuracion/*" element={<SettingsPage />} />
                {DocumentEditorDemo && (
                  <Route
                    path="dev/editor-documento"
                    element={<DocumentEditorDemo />}
                  />
                )}
                <Route path="*" element={<NotFoundPage />} />
              </Route>
            </Routes>
          </Suspense>
        </SessionProvider>
      </ThemedRoot>
    </BrowserRouter>
  );
}
