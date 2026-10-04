import {
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import {useTranslation} from '@/shared/i18n';
import {PageHeader, TabPanel, Tabs} from '@/shared/ui';
import {CarteraDocumentsView} from './CarteraDocumentsView';
import {CarteraView} from './CarteraView';
import {ExportPanel} from './ExportPanel';

const VIEWS = [
  {value: 'clients', path: 'clientes'},
  {value: 'suppliers', path: 'proveedores'},
  {value: 'export', path: 'exportar'},
] as const;
type View = (typeof VIEWS)[number]['value'];

/**
 * Reportes (§4.13): cartera de clientes and de proveedores with ageing (and the drill-down to each tercero's
 * documents), and the Exportar panel for every report. One sub-route each (/reportes/clientes…).
 */
export function ReportsPage() {
  const {t} = useTranslation();
  const navigate = useNavigate();
  const {pathname} = useLocation();
  const current: View =
    VIEWS.find((v) => pathname.split('/').includes(v.path))?.value ?? 'clients';
  const drilling = /\/reportes\/(clientes|proveedores)\/[^/]+/.test(pathname);

  const routes = (
    <Routes>
      <Route index element={<Navigate to="clientes" replace />} />
      <Route path="clientes" element={<CarteraView side="clients" />} />
      <Route
        path="clientes/:terceroId"
        element={<CarteraDocumentsView side="clients" />}
      />
      <Route path="proveedores" element={<CarteraView side="suppliers" />} />
      <Route
        path="proveedores/:terceroId"
        element={<CarteraDocumentsView side="suppliers" />}
      />
      <Route path="exportar" element={<ExportPanel />} />
      <Route path="*" element={<Navigate to="clientes" replace />} />
    </Routes>
  );

  return (
    <>
      {!drilling && (
        <>
          <PageHeader
            title={t('reports.title')}
            subtitle={t('reports.subtitle')}
          />
          <Tabs
            id="reports"
            label={t('reports.tabs.label')}
            value={current}
            options={VIEWS.map((v) => ({
              value: v.value,
              label: t(`reports.tabs.${v.value}`),
            }))}
            onChange={(value) =>
              navigate(
                `/reportes/${VIEWS.find((v) => v.value === value)?.path ?? 'clientes'}`,
              )
            }
          />
        </>
      )}
      {drilling ? (
        routes
      ) : (
        <TabPanel id="reports" value={current}>
          {routes}
        </TabPanel>
      )}
    </>
  );
}
