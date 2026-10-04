import {useEffect, useState} from 'react';
import {Link} from 'react-router-dom';
import {can, useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {formatDate, formatMoney} from '@/shared/lib';
import {Alert, Card, ErrorState, Loading, PageHeader} from '@/shared/ui';
import {
  fetchDashboard,
  fetchResolutionStatus,
  type Dashboard,
  type ResolutionStatus,
} from '../api/dashboardApi';
import './dashboard.css';

/** One number a small company owner looks at: a title, the figure, and a line under it. */
function Tile({
  title,
  figure,
  children,
}: {
  title: string;
  figure: string;
  children?: React.ReactNode;
}) {
  return (
    <Card>
      <div className="dashboard-tile">
        <h2>{title}</h2>
        <p className="dashboard-figure">{figure}</p>
        {children}
      </div>
    </Card>
  );
}

/** The invoicing resolution's own warning (§4.1): running out, or not usable at all. */
function resolutionMessage(
  status: ResolutionStatus,
  t: ReturnType<typeof useTranslation>['t'],
): string | null {
  switch (status.status) {
    case 'missing':
      return t('reports.dashboard.resolution.missing');
    case 'expired':
      return t('reports.dashboard.resolution.expired');
    case 'exhausted':
      return t('reports.dashboard.resolution.exhausted');
    case 'not_yet_valid':
      return t('reports.dashboard.resolution.notYetValid');
    default:
      return status.warning
        ? t('reports.dashboard.resolution.warning', {
            numbers: status.numbers_left,
            days: status.days_left,
          })
        : null;
  }
}

/**
 * The home screen (§4.13): cartera, the month's sales and purchases, cash and banks, the resolution's warning and the
 * three documents a small company creates most. One request for the numbers; what is the books' (cash and banks) is
 * shown only to who may view them, as the server decides.
 */
export function DashboardPage() {
  const {t} = useTranslation();
  const {session} = useSession();
  const [figures, setFigures] = useState<Dashboard | null>(null);
  const [failed, setFailed] = useState(false);
  const [resolution, setResolution] = useState<ResolutionStatus | null>(null);
  const [attempt, setAttempt] = useState(0);
  const writer = can(session, 'WRITE_DOCUMENTS');

  useEffect(() => {
    let cancelled = false;
    fetchDashboard()
      .then((data) => {
        if (cancelled) return;
        setFigures(data);
        setFailed(false);
      })
      .catch(() => !cancelled && setFailed(true));
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  // Only who creates invoices needs to hear about the resolution; a failed read just shows no warning.
  useEffect(() => {
    if (!writer) return;
    let cancelled = false;
    fetchResolutionStatus()
      .then((status) => !cancelled && setResolution(status))
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [writer]);

  const warning = resolution ? resolutionMessage(resolution, t) : null;

  return (
    <>
      <PageHeader
        title={t('reports.dashboard.title')}
        subtitle={
          figures
            ? t('reports.dashboard.subtitle', {date: formatDate(figures.as_of)})
            : undefined
        }
      />
      {warning && (
        <Alert kind="warning">
          {warning}{' '}
          {can(session, 'MANAGE_SETTINGS') && (
            <Link to="/configuracion?tab=resolution">
              {t('reports.dashboard.resolution.fix')}
            </Link>
          )}
        </Alert>
      )}
      {failed && !figures ? (
        <ErrorState
          message={t('reports.dashboard.loadFailed')}
          onRetry={() => setAttempt((n) => n + 1)}
        />
      ) : !figures ? (
        <Loading />
      ) : (
        <div className="dashboard-grid">
          <Tile
            title={t('reports.dashboard.clients.title')}
            figure={formatMoney(figures.clients_total)}
          >
            <p className="dashboard-sub">
              <span>{t('reports.dashboard.clients.overdue')}</span>
              <span
                className={
                  Number(figures.clients_overdue) > 0 ? 'is-overdue' : undefined
                }
              >
                {formatMoney(figures.clients_overdue)}
              </span>
            </p>
            <Link to="/reportes/clientes">
              {t('reports.dashboard.viewReport')}
            </Link>
          </Tile>
          <Tile
            title={t('reports.dashboard.suppliers.title')}
            figure={formatMoney(figures.suppliers_total)}
          >
            <p className="dashboard-sub">
              <span>{t('reports.dashboard.suppliers.overdue')}</span>
              <span
                className={
                  Number(figures.suppliers_overdue) > 0
                    ? 'is-overdue'
                    : undefined
                }
              >
                {formatMoney(figures.suppliers_overdue)}
              </span>
            </p>
            <Link to="/reportes/proveedores">
              {t('reports.dashboard.viewReport')}
            </Link>
          </Tile>
          <Tile
            title={t('reports.dashboard.sales.title')}
            figure={formatMoney(figures.sales_month)}
          >
            <p className="dashboard-sub">
              <span>{t('reports.dashboard.sales.hint')}</span>
              <span>
                {t('reports.dashboard.sales.count', {
                  count: figures.sales_month_count,
                })}
              </span>
            </p>
          </Tile>
          <Tile
            title={t('reports.dashboard.purchases.title')}
            figure={formatMoney(figures.purchases_month)}
          >
            <p className="dashboard-sub">
              <span>{t('reports.dashboard.purchases.hint')}</span>
              <span>
                {t('reports.dashboard.purchases.count', {
                  count: figures.purchases_month_count,
                })}
              </span>
            </p>
          </Tile>
          {figures.cash_and_banks != null && (
            <Tile
              title={t('reports.dashboard.cash.title')}
              figure={formatMoney(figures.cash_and_banks)}
            >
              <p className="dashboard-sub">
                <span>{t('reports.dashboard.cash.hint')}</span>
              </p>
            </Tile>
          )}
        </div>
      )}
      {writer && (
        <Card title={t('reports.dashboard.quick.title')}>
          <div className="dashboard-quick">
            <Link className="btn btn-primary" to="/facturas-venta/nueva">
              {t('reports.dashboard.quick.invoice')}
            </Link>
            <Link className="btn btn-secondary" to="/recibos-caja/nuevo">
              {t('reports.dashboard.quick.receipt')}
            </Link>
            <Link className="btn btn-secondary" to="/facturas-compra/nueva">
              {t('reports.dashboard.quick.purchase')}
            </Link>
          </div>
        </Card>
      )}
    </>
  );
}
