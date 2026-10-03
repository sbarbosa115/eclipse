import {
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {EmptyState, PageHeader, TabPanel, Tabs} from '@/shared/ui';
import {BalanceSheetView} from './BalanceSheetView';
import {IncomeStatementView} from './IncomeStatementView';
import {JournalView} from './JournalView';
import {TrialBalanceView} from './TrialBalanceView';
import './ledger.css';

const VIEWS = [
  {value: 'journal', path: 'diario', view: JournalView},
  {value: 'trialBalance', path: 'balance-prueba', view: TrialBalanceView},
  {
    value: 'incomeStatement',
    path: 'estado-resultados',
    view: IncomeStatementView,
  },
  {value: 'balanceSheet', path: 'balance-general', view: BalanceSheetView},
] as const;
type View = (typeof VIEWS)[number]['value'];

/**
 * Libros contables (§4.13, §9 Q25): libro diario, balance de prueba, estado de resultados and balance general, one
 * sub-route each (/contabilidad/diario…), for the owner and the accountant.
 */
export function LedgerPage() {
  const {t} = useTranslation();
  const {session} = useSession();
  const navigate = useNavigate();
  const {pathname} = useLocation();
  const keeper = session?.role === 'owner' || session?.role === 'accountant';
  const current: View =
    VIEWS.find((v) => pathname.split('/').includes(v.path))?.value ?? 'journal';

  return (
    <>
      <PageHeader title={t('ledger.title')} subtitle={t('ledger.subtitle')} />
      {!session ? null : !keeper ? (
        <EmptyState>{t('ledger.noAccess')}</EmptyState>
      ) : (
        <>
          <Tabs
            id="ledger"
            label={t('ledger.tabs.label')}
            value={current}
            options={VIEWS.map((v) => ({
              value: v.value,
              label: t(`ledger.tabs.${v.value}`),
            }))}
            onChange={(value) =>
              navigate(
                `/contabilidad/${VIEWS.find((v) => v.value === value)?.path ?? 'diario'}`,
              )
            }
          />
          <TabPanel id="ledger" value={current}>
            <Routes>
              <Route index element={<Navigate to="diario" replace />} />
              {VIEWS.map((v) => (
                <Route key={v.path} path={v.path} element={<v.view />} />
              ))}
              <Route path="*" element={<Navigate to="diario" replace />} />
            </Routes>
          </TabPanel>
        </>
      )}
    </>
  );
}
