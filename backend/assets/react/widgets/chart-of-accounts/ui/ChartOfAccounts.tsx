import {useEffect, useState} from 'react';
import {can, useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {
  Actions,
  Alert,
  Badge,
  Button,
  DataTable,
  EmptyState,
  ErrorState,
  FilterBar,
  IconButton,
  Loading,
  Pager,
  TabIntro,
} from '@/shared/ui';
import {
  fetchChart,
  fetchUses,
  type Account,
  type AccountPage,
} from '../api/chartApi';
import {movedText, type AccountUse} from '../lib/uses';
import {AddAccountModal, EditAccountModal} from './AccountForms';
import './chart.css';

const CLASSES = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];

type Open = {kind: 'add'; parent: Account} | {kind: 'edit'; account: Account};

/**
 * Subdivided without moving what pointed at it (move_references false): active, of a level that posts, but no longer
 * postable, and a rule, tax or payment method still names it.
 */
function leftWithoutDefault(account: Account, used: Set<string>): boolean {
  return (
    account.active &&
    !account.postable &&
    (account.level === 'subaccount' || account.level === 'auxiliary') &&
    used.has(account.id)
  );
}

/**
 * Configuración › Plan de cuentas (§4.13): the PUC with the company's own accounts, searched by code or name. The
 * owner and the accountant add sub-accounts and auxiliares, rename their own and (de)activate any.
 */
export function ChartOfAccounts() {
  const {t} = useTranslation();
  const {session} = useSession();
  const keeper = can(session, 'MANAGE_BOOKS');
  const [q, setQ] = useState('');
  const [accountClass, setAccountClass] = useState('');
  const [page, setPage] = useState(1);
  const [data, setData] = useState<AccountPage | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [open, setOpen] = useState<Open | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [uses, setUses] = useState<AccountUse[]>([]);
  const [usesAttempt, setUsesAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    fetchUses()
      .then((answer) => {
        if (!cancelled) setUses(answer);
      })
      // Only the warning and the badge need them: the chart works without.
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [usesAttempt]);
  const used = new Set(uses.map((u) => u.accountId));

  useEffect(() => {
    let cancelled = false;
    fetchChart({q, accountClass, page})
      .then((answer) => {
        if (cancelled) return;
        setData(answer);
        setFailed(false);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, [q, accountClass, page, attempt]);

  const filtered = q !== '' || accountClass !== '';
  const showAll = () => {
    setQ('');
    setAccountClass('');
    setPage(1);
  };

  const replace = (account: Account) =>
    setData((current) =>
      current
        ? {
            ...current,
            items: current.items.map((a) =>
              a.id === account.id ? account : a,
            ),
          }
        : current,
    );

  return (
    <>
      <TabIntro>{t('ledger.chart.intro')}</TabIntro>
      {!keeper && session && <Alert>{t('ledger.chart.readOnly')}</Alert>}
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <FilterBar
        search={q}
        onSearch={(value) => {
          setQ(value);
          setPage(1);
        }}
        searchPlaceholder={t('ledger.chart.searchPlaceholder')}
        filters={[
          {
            name: 'class',
            label: t('ledger.chart.class'),
            value: accountClass,
            onChange: (value) => {
              setAccountClass(value);
              setPage(1);
            },
            options: [
              {value: '', label: t('ledger.chart.allClasses')},
              ...CLASSES.map((c) => ({value: c, label: c})),
            ],
          },
        ]}
      />
      {failed ? (
        <ErrorState
          message={t('common.loadFailed')}
          onRetry={() => setAttempt((n) => n + 1)}
        />
      ) : !data ? (
        <Loading />
      ) : data.items.length === 0 ? (
        <EmptyState
          action={
            filtered && (
              <Button variant="ghost" onClick={showAll}>
                {t('common.showAll')}
              </Button>
            )
          }
        >
          {filtered ? t('ledger.chart.filteredEmpty') : t('ledger.chart.empty')}
        </EmptyState>
      ) : (
        <>
          <DataTable
            columns={[
              t('ledger.chart.code'),
              t('ledger.chart.name'),
              t('ledger.chart.nature'),
              t('ledger.chart.level'),
            ]}
            rows={data.items}
            actions={keeper}
            renderRow={(account) => (
              <tr key={account.id} className={`chart-level-${account.level}`}>
                <td className="chart-code">{account.code}</td>
                <td className="chart-name">
                  {account.name}
                  {!account.standard && (
                    <Badge value="info">{t('ledger.chart.own')}</Badge>
                  )}
                  {!account.active && (
                    <Badge value="neutral">{t('ledger.chart.inactive')}</Badge>
                  )}
                  {leftWithoutDefault(account, used) && (
                    <Badge value="invalid">
                      {t('ledger.chart.subdivide.badge')}
                    </Badge>
                  )}
                </td>
                <td>{t(`ledger.natures.${account.nature}`)}</td>
                <td>{t(`ledger.levels.${account.level}`)}</td>
                {keeper && (
                  <Actions>
                    {account.code.length >= 4 && (
                      <IconButton
                        icon="plus"
                        label={t('ledger.chart.addUnder', {code: account.code})}
                        onClick={() => setOpen({kind: 'add', parent: account})}
                      />
                    )}
                    <IconButton
                      icon="pencil"
                      label={t('ledger.chart.edit', {code: account.code})}
                      onClick={() => setOpen({kind: 'edit', account})}
                    />
                  </Actions>
                )}
              </tr>
            )}
          />
          <Pager data={data} onPage={setPage} />
        </>
      )}
      {open?.kind === 'add' && (
        <AddAccountModal
          parent={open.parent}
          uses={uses.filter((u) => u.accountId === open.parent.id)}
          onClose={() => setOpen(null)}
          onSaved={(account) => {
            setOpen(null);
            const moved = account.moved ? movedText(t, account.moved) : null;
            setNotice(
              [
                t('ledger.chart.created', {code: account.code}),
                moved &&
                  t('ledger.chart.subdivide.moved', {
                    code: account.code,
                    what: moved,
                  }),
              ]
                .filter(Boolean)
                .join(' '),
            );
            setAttempt((n) => n + 1);
            setUsesAttempt((n) => n + 1);
          }}
        />
      )}
      {open?.kind === 'edit' && (
        <EditAccountModal
          account={open.account}
          onClose={() => setOpen(null)}
          onSaved={(account) => {
            setOpen(null);
            setNotice(t('ledger.chart.saved', {code: account.code}));
            replace(account);
          }}
        />
      )}
    </>
  );
}
