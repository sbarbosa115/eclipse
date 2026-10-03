import {useEffect, useState} from 'react';
import {useSession} from '@/entities/session';
import {useTranslation} from '@/shared/i18n';
import {
  Actions,
  Alert,
  DataTable,
  ErrorState,
  IconButton,
  Loading,
  TabIntro,
} from '@/shared/ui';
import {fetchRules, type PostingRule} from '../api/rulesApi';
import {ChangeRuleModal} from './ChangeRuleModal';
import {LockDateCard} from './LockDateCard';
import './rules.css';

/**
 * Configuración › Reglas contables (§5): which PUC account each concept posts to, and the fecha de bloqueo. The owner
 * and the accountant change them; every change is logged.
 */
export function PostingRules() {
  const {t} = useTranslation();
  const {session} = useSession();
  const keeper = session?.role === 'owner' || session?.role === 'accountant';
  const [rules, setRules] = useState<PostingRule[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [open, setOpen] = useState<PostingRule | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchRules()
      .then(({items}) => {
        if (cancelled) return;
        setRules(items);
        setFailed(false);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  const label = (concept: string) => t(`ledger.rules.concepts.${concept}`);

  return (
    <>
      <TabIntro>{t('ledger.rules.intro')}</TabIntro>
      {!keeper && session && <Alert>{t('ledger.rules.readOnly')}</Alert>}
      <LockDateCard keeper={keeper} />
      <Alert kind="success" onDismiss={() => setNotice(null)}>
        {notice}
      </Alert>
      <div className="posting-rules-table">
        {failed ? (
          <ErrorState
            message={t('common.loadFailed')}
            onRetry={() => setAttempt((n) => n + 1)}
          />
        ) : !rules ? (
          <Loading />
        ) : (
          <DataTable
            columns={[t('ledger.rules.concept'), t('ledger.rules.account')]}
            rows={rules}
            actions={keeper}
            renderRow={(rule) => (
              <tr key={rule.concept}>
                <td>{label(rule.concept)}</td>
                <td>{`${rule.account_code} ${rule.account_name}`}</td>
                {keeper && (
                  <Actions>
                    <IconButton
                      icon="pencil"
                      label={t('ledger.rules.change', {
                        concept: label(rule.concept),
                      })}
                      onClick={() => setOpen(rule)}
                    />
                  </Actions>
                )}
              </tr>
            )}
          />
        )}
      </div>
      {open && (
        <ChangeRuleModal
          rule={open}
          conceptLabel={label(open.concept)}
          onClose={() => setOpen(null)}
          onSaved={(saved) => {
            setOpen(null);
            setRules(
              (current) =>
                current?.map((r) =>
                  r.concept === saved.concept ? saved : r,
                ) ?? null,
            );
            setNotice(
              t('ledger.rules.saved', {
                concept: label(saved.concept),
                code: saved.account_code,
              }),
            );
          }}
        />
      )}
    </>
  );
}
