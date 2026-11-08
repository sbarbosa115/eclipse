import {
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import {can, useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {EmptyState, PageHeader, TabPanel, Tabs} from '@/shared/ui';
import {VatView} from './VatView';
import {WithholdingsView} from './WithholdingsView';
import './taxReports.css';

/**
 * The page's tabs, one sub-route each (/reportes/impuestos/<path>). A new tab (the exógena, item 9) is one more entry
 * here and its `taxReports.tabs.<value>` label.
 */
const VIEWS = [
  {
    value: 'practiced',
    path: 'retenciones-practicadas',
    element: <WithholdingsView key="practiced" side="practiced" />,
  },
  {
    value: 'suffered',
    path: 'retenciones-sufridas',
    element: <WithholdingsView key="suffered" side="suffered" />,
  },
  {value: 'vat', path: 'iva', element: <VatView />},
] as const;
type View = (typeof VIEWS)[number]['value'];
const FIRST = VIEWS[0].path;

/**
 * Reportes › Impuestos (observations PRD §4.7): the working papers of the tax returns, for who may view the books
 * (owner and accountant). Retenciones practicadas and sufridas, and IVA, each with its CSV and PDF.
 */
export function TaxReportsPage() {
  const {t} = useTranslation();
  const {session} = useSession();
  const navigate = useNavigate();
  const {pathname, search} = useLocation();
  const keeper = can(session, 'VIEW_BOOKS');
  const current: View =
    VIEWS.find((v) => pathname.split('/').includes(v.path))?.value ??
    VIEWS[0].value;

  return (
    <>
      <PageHeader
        title={t('taxReports.title')}
        subtitle={t('taxReports.subtitle')}
      />
      {!session ? null : !keeper ? (
        <EmptyState>{t('taxReports.noAccess')}</EmptyState>
      ) : (
        <>
          <Tabs
            id="tax-reports"
            label={t('taxReports.tabs.label')}
            value={current}
            options={VIEWS.map((v) => ({
              value: v.value,
              label: t(`taxReports.tabs.${v.value}`),
            }))}
            onChange={(value) =>
              // The period travels with the tab: the same months read as retenciones or as IVA.
              navigate(
                `/reportes/impuestos/${VIEWS.find((v) => v.value === value)?.path ?? FIRST}${search}`,
              )
            }
          />
          <TabPanel id="tax-reports" value={current}>
            <Routes>
              <Route index element={<Navigate to={FIRST} replace />} />
              {VIEWS.map((v) => (
                <Route key={v.path} path={v.path} element={v.element} />
              ))}
              <Route path="*" element={<Navigate to={FIRST} replace />} />
            </Routes>
          </TabPanel>
        </>
      )}
    </>
  );
}
